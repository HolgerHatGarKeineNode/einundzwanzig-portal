<?php

use App\Models\MeetupEvent;
use App\Models\MeetupEventNostrRsvp;
use App\Models\MeetupEventRsvpTime;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Migration 2026_09_18_090000 — every portal answer gets a time (D12a)
|--------------------------------------------------------------------------
|
| RefreshDatabase runs the migration against an EMPTY table, which proves nothing about
| the backfill. Each test therefore builds the pre-migration state first — entries on the
| lists, no rows — and calls up() against it. The migration is idempotent, so calling it
| again after RefreshDatabase is exactly what production does when it is deployed.
*/

function backfillRsvpTimesMigration(): object
{
    return require base_path('database/migrations/2026_09_18_090000_backfill_meetup_event_rsvp_times.php');
}

it('gives every account on an attendee list a time, and nobody else', function () {
    $this->freezeTime();

    $attending = User::factory()->create();
    $maybe = User::factory()->create();
    $uninvolved = User::factory()->create();
    $deleted = User::factory()->create();
    $deletedId = $deleted->id;
    $deleted->forceDelete();

    $event = MeetupEvent::factory()->create([
        'attendees' => ["id_{$attending->id}|Anna", "id_{$deletedId}|Weg", 'anon_sessionabc|Gast'],
        'might_attendees' => ["id_{$maybe->id}|Mara"],
    ]);
    $untouched = MeetupEvent::factory()->create(['attendees' => [], 'might_attendees' => []]);

    MeetupEventRsvpTime::query()->delete();

    backfillRsvpTimesMigration()->up();

    expect(MeetupEventRsvpTime::query()->pluck('user_id')->sort()->values()->all())->toBe(collect([$attending->id, $maybe->id])->sort()->values()->all())
        ->and(MeetupEventRsvpTime::query()->where('user_id', $attending->id)->sole())
        ->meetup_event_id->toBe($event->id)
        ->and(MeetupEventRsvpTime::query()->where('user_id', $attending->id)->sole()->answered_at->timestamp)->toBe(now()->timestamp)
        ->and(MeetupEventRsvpTime::query()->where('meetup_event_id', $untouched->id)->exists())->toBeFalse()
        ->and(MeetupEventRsvpTime::query()->where('user_id', $uninvolved->id)->exists())->toBeFalse();
});

it('leaves a real answer given after the deployment with its own, later time', function () {
    $user = User::factory()->create();
    $event = MeetupEvent::factory()->create(['attendees' => ["id_{$user->id}|Anna"], 'might_attendees' => []]);

    $answeredAt = now()->addHour()->startOfSecond();
    MeetupEventRsvpTime::query()->delete();
    MeetupEventRsvpTime::query()->create(['meetup_event_id' => $event->id, 'user_id' => $user->id, 'answered_at' => $answeredAt]);

    backfillRsvpTimesMigration()->up();

    expect(MeetupEventRsvpTime::query()->sole()->answered_at->timestamp)->toBe($answeredAt->timestamp)
        ->and(MeetupEventRsvpTime::query()->count())->toBe(1);
});

it('protects the backfilled answer from an older Nostr RSVP, and yields to a newer one', function () {
    $user = User::factory()->create(['name' => 'Alte Zusage']);
    $event = MeetupEvent::factory()->create(['attendees' => ["id_{$user->id}|Alte Zusage"], 'might_attendees' => []]);
    MeetupEventRsvpTime::query()->delete();

    backfillRsvpTimesMigration()->up();

    $older = MeetupEventNostrRsvp::factory()->create([
        'meetup_event_id' => $event->id,
        'user_id' => $user->id,
        'status' => 'declined',
        'rsvp_created_at' => now()->subYear()->timestamp,
    ]);
    $older->forceFill(['updated_at' => now()->subYear()])->save();

    expect($event->fresh()->attendeesCount())->toBe(1)
        ->and($event->fresh()->rsvpStatusFor($user)->value)->toBe('attending');

    // A Nostr answer given AFTER the deployment does override it — otherwise the test
    // above would pass on a merge that ignores Nostr answers altogether.
    $older->forceFill([
        'rsvp_created_at' => now()->addMinute()->timestamp,
        'updated_at' => now()->addMinute(),
    ])->save();

    expect($event->fresh()->attendeesCount())->toBe(0)
        ->and($event->fresh()->rsvpStatusFor($user)->value)->toBe('none');
});

it('can run twice without writing a second row', function () {
    $user = User::factory()->create();
    MeetupEvent::factory()->create(['attendees' => ["id_{$user->id}|Anna"], 'might_attendees' => []]);
    MeetupEventRsvpTime::query()->delete();

    backfillRsvpTimesMigration()->up();
    backfillRsvpTimesMigration()->up();

    expect(DB::table('meetup_event_rsvp_times')->count())->toBe(1);
});
