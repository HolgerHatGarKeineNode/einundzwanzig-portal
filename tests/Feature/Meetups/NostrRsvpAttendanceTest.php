<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\MeetupEventNostrRsvp;
use App\Models\MeetupEventRsvpTime;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Counting Nostr RSVPs next to portal answers (D12a)
|--------------------------------------------------------------------------
|
| A Nostr RSVP of a key LINKED to an account is that user's answer; of his portal answer
| and his Nostr answer the newer one wins. An RSVP of an UNLINKED key is counted apart
| ("+N via Nostr") and never named. The rows here are created directly — how they get
| into the table is IngestNostrRsvpsTest's business.
*/

beforeEach(function () {
    Http::fake(['verein.einundzwanzig.space/api/members/*' => Http::response([], 200)]);

    $this->city = City::factory()->create(['country_id' => Country::factory()->create(['code' => 'de'])->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'rsvp_enabled' => true, 'attendees_public' => true]);
    $this->event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => now()->addDays(3),
        'attendees' => ['id_999|Portal Pleb'],
        'might_attendees' => [],
    ]);
});

/**
 * A stored Nostr RSVP, as the ingest leaves it: `updated_at` is when the portal stored it.
 */
function storedNostrRsvp(MeetupEvent $event, string $status, int $createdAt, ?User $linkedTo = null, ?int $storedAt = null): MeetupEventNostrRsvp
{
    $rsvp = MeetupEventNostrRsvp::factory()->create([
        'meetup_event_id' => $event->id,
        'status' => $status,
        'rsvp_created_at' => $createdAt,
        'user_id' => $linkedTo?->id,
    ]);

    $rsvp->forceFill(['updated_at' => Illuminate\Support\Carbon::createFromTimestamp($storedAt ?? $createdAt)])->save();

    return $rsvp;
}

function portalAnsweredAt(MeetupEvent $event, User $user, int $timestamp): void
{
    MeetupEventRsvpTime::query()->create([
        'meetup_event_id' => $event->id,
        'user_id' => $user->id,
        'answered_at' => Illuminate\Support\Carbon::createFromTimestamp($timestamp),
    ]);
}

function eventListRow(MeetupEvent $event): array
{
    return collect(test()->getJson('/api/meetup-events')->assertSuccessful()->json())->firstWhere('id', $event->id);
}

it('counts a linked Nostr RSVP as the user\'s answer and an unlinked one apart, without its name', function () {
    $linked = User::factory()->create(['name' => 'Linked Larissa']);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp, $linked);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp);
    storedNostrRsvp($this->event, 'tentative', now()->subHour()->timestamp);
    storedNostrRsvp($this->event, 'declined', now()->subHour()->timestamp);

    expect(eventListRow($this->event))->toMatchArray([
        'attendees' => 2,
        'might_attendees' => 0,
        'nostr_attendees' => 1,
        'nostr_might_attendees' => 1,
    ]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/meetup-events/{$this->event->id}/rsvp")
        ->assertSuccessful()
        ->assertExactJson([
            'status' => 'none',
            'attendees' => 2,
            'might_attendees' => 0,
            'attendee_names' => ['Portal Pleb', 'Linked Larissa'],
            'nostr_attendees' => 1,
            'nostr_might_attendees' => 1,
        ]);
});

it('lets the newer of the two answers of a linked user win', function (string $newer, array $expected) {
    $user = User::factory()->create(['name' => 'Two Channels']);
    $this->event->update(['might_attendees' => ["id_{$user->id}|Two Channels"]]);

    $old = now()->subHours(2)->timestamp;
    $new = now()->subHour()->timestamp;

    portalAnsweredAt($this->event, $user, $newer === 'portal' ? $new : $old);
    storedNostrRsvp($this->event, 'declined', $newer === 'nostr' ? $new : $old, $user);

    Sanctum::actingAs($user);

    $this->getJson("/api/meetup-events/{$this->event->id}/rsvp")
        ->assertSuccessful()
        ->assertJson($expected);
})->with([
    'the portal answer is newer' => ['portal', ['status' => 'maybe', 'attendees' => 1, 'might_attendees' => 1]],
    'the Nostr answer is newer' => ['nostr', ['status' => 'none', 'attendees' => 1, 'might_attendees' => 0]],
]);

it('lets the portal answer win an exact tie', function () {
    $user = User::factory()->create();
    $this->event->update(['might_attendees' => ["id_{$user->id}|Tie"]]);
    $moment = now()->subHour()->timestamp;
    portalAnsweredAt($this->event, $user, $moment);
    storedNostrRsvp($this->event, 'accepted', $moment, $user);

    expect($this->event->fresh()->rsvpStatusFor($user)->value)->toBe('maybe');
});

it('treats a portal answer from before the timestamp table as older than any Nostr answer', function () {
    $user = User::factory()->create();
    $this->event->update(['attendees' => ['id_999|Portal Pleb', "id_{$user->id}|Legacy"]]);
    storedNostrRsvp($this->event, 'declined', now()->subYear()->timestamp, $user);

    expect(eventListRow($this->event)['attendees'])->toBe(1)
        ->and($this->event->fresh()->rsvpStatusFor($user)->value)->toBe('none');
});

it('stamps a REST answer, which then outranks the older Nostr answer, without changing the list format', function () {
    $this->freezeTime();
    Sanctum::actingAs($user = User::factory()->create(['name' => 'Rest Rita']));
    storedNostrRsvp($this->event, 'accepted', now()->subMinutes(10)->timestamp, $user);

    $this->getJson("/api/meetup-events/{$this->event->id}/rsvp")->assertJson(['status' => 'attending', 'attendees' => 2]);

    $this->postJson("/api/meetup-events/{$this->event->id}/rsvp", ['status' => 'maybe'])
        ->assertSuccessful()
        ->assertJson(['status' => 'maybe', 'attendees' => 1, 'might_attendees' => 1]);

    expect($this->event->fresh()->might_attendees)->toBe(["id_{$user->id}|Rest Rita"])
        ->and(MeetupEventRsvpTime::query()->where('user_id', $user->id)->sole()->answered_at->timestamp)->toBe(now()->timestamp);
});

it('does not let a Nostr answer dated into the future outrank a later portal answer', function () {
    Sanctum::actingAs($user = User::factory()->create());

    // Stored a minute ago, signed by a clock running 13 minutes fast — inside the skew
    // the ingest accepts.
    storedNostrRsvp($this->event, 'accepted', now()->addMinutes(12)->timestamp, $user, storedAt: now()->subMinute()->timestamp);

    $this->postJson("/api/meetup-events/{$this->event->id}/rsvp", ['status' => 'none'])
        ->assertSuccessful()
        ->assertJson(['status' => 'none', 'attendees' => 1]);
});

it('hides every Nostr count where the attendee list is not public', function () {
    $this->meetup->update(['attendees_public' => false]);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp);

    expect(eventListRow($this->event))->toMatchArray(['nostr_attendees' => null, 'nostr_might_attendees' => null]);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/meetup-events/{$this->event->id}/rsvp")
        ->assertJson(['nostr_attendees' => null, 'nostr_might_attendees' => null]);

    Sanctum::actingAs(User::query()->find($this->meetup->created_by));
    $this->getJson("/api/meetup-events/{$this->event->id}/rsvp")
        ->assertJson(['nostr_attendees' => 1, 'nostr_might_attendees' => 0]);
});

it('carries the Nostr counts in the next event of the public meetup list', function () {
    storedNostrRsvp($this->event, 'tentative', now()->subHour()->timestamp);

    $row = collect($this->getJson('/api/meetups')->assertSuccessful()->json())->firstWhere('id', $this->meetup->id);

    expect($row['next_event'])->toMatchArray([
        'attendees' => 1,
        'might_attendees' => 0,
        'nostr_attendees' => 0,
        'nostr_might_attendees' => 1,
    ]);
});

it('loads no RSVP rows for a next event that has none, on the public meetup list', function () {
    $withRsvp = Meetup::factory()->create(['city_id' => $this->city->id]);
    $withRsvpEvent = MeetupEvent::factory()->create(['meetup_id' => $withRsvp->id, 'start' => now()->addDay()]);
    storedNostrRsvp($withRsvpEvent, 'accepted', now()->subHour()->timestamp);

    $rowLoads = [];
    Illuminate\Support\Facades\DB::listen(function ($query) use (&$rowLoads): void {
        if (preg_match('/^select \* from "meetup_event_(nostr_rsvps|rsvp_times)"/', $query->sql) === 1) {
            $rowLoads[] = $query->sql;
        }
    });

    $this->getJson('/api/meetups')->assertSuccessful();

    // Two meetups with an upcoming event, one of them with an unlinked RSVP: exactly one
    // row load, and no answer-time load, because nothing is linked.
    expect($rowLoads)->toHaveCount(1)
        ->and($rowLoads[0])->toContain('meetup_event_nostr_rsvps');
});

it('shows "+N via Nostr" next to the counts on the meetup page, and nothing when there is none', function () {
    $other = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id, 'start' => now()->addDays(5), 'attendees' => [], 'might_attendees' => []]);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp);
    storedNostrRsvp($this->event, 'tentative', now()->subHour()->timestamp);

    $html = Livewire::test('meetups.landingpage', ['meetup' => $this->meetup])->assertOk()->html();

    $document = Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);

    expect(array_map(fn ($node) => trim($node->textContent), iterator_to_array($document->querySelectorAll('[data-testid="nostr-attendees"]'))))->toBe(['+2 via Nostr'])
        ->and(array_map(fn ($node) => trim($node->textContent), iterator_to_array($document->querySelectorAll('[data-testid="nostr-might-attendees"]'))))->toBe(['+1 via Nostr'])
        ->and($other->nostrRsvps()->count())->toBe(0);
});

it('shows the linked user by name and the unlinked ones as "+N via Nostr" on the event page', function () {
    $linked = User::factory()->create(['name' => 'Linked Larissa']);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp, $linked);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp);

    Livewire::test('meetups.landingpage-event', ['event' => $this->event])
        ->assertOk()
        ->assertSee('Linked Larissa')
        ->assertSeeHtml('data-testid="nostr-attendees"')
        ->assertSee('+1 via Nostr')
        ->assertSet('nostrAttendees', 1);
});

it('stamps an answer given on the event page, so withdrawing there beats an older Nostr RSVP', function () {
    $user = User::factory()->create(['name' => 'Web Walter']);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp, $user);
    $this->actingAs($user);

    Livewire::test('meetups.landingpage-event', ['event' => $this->event])
        ->assertSet('willShowUp', true)
        ->call('cannotCome')
        ->assertSet('willShowUp', false)
        ->assertDontSee('Web Walter');

    expect($this->event->fresh()->attendeesCount())->toBe(1)
        ->and(MeetupEventRsvpTime::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('shows no Nostr count to a guest where the attendee list is not public', function () {
    $this->meetup->update(['attendees_public' => false]);
    storedNostrRsvp($this->event, 'accepted', now()->subHour()->timestamp);

    Livewire::test('meetups.landingpage-event', ['event' => $this->event->fresh()])
        ->assertOk()
        ->assertDontSeeHtml('data-testid="nostr-attendees"')
        ->assertSet('nostrAttendees', 0);

    expect(Livewire::test('meetups.landingpage', ['meetup' => $this->meetup->fresh()])->html())
        ->not->toContain('data-testid="nostr-attendees"');
});
