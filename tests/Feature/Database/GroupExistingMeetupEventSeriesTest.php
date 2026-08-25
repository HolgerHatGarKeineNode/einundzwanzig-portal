<?php

use App\Models\ApiChange;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Die rückwirkende Gruppierung bestehender Serien.
 *
 * Bestandsdaten werden per Query Builder gesetzt, nicht über Eloquent: das Szenario ist
 * "so lag es vor der Migration in der Tabelle", und die Observer sollen es weder
 * verändern noch melden.
 */
function legacyEvent(int $meetupId, int $userId, string $start, string $createdAt, array $overrides = []): int
{
    return DB::table('meetup_events')->insertGetId([
        'meetup_id' => $meetupId,
        'created_by' => $userId,
        'start' => $start,
        'title' => 'Stammtisch',
        'location' => 'Marktplatz',
        'description' => 'Wöchentlicher Stammtisch',
        'link' => 'https://einundzwanzig.space',
        'attendees' => '[]',
        'might_attendees' => '[]',
        'recurrence_group' => null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        ...$overrides,
    ]);
}

function runGroupingMigration(): object
{
    /** @var object $migration */
    $migration = require database_path('migrations/2026_08_25_194948_group_existing_meetup_event_series.php');

    $migration->up();

    return $migration;
}

it('groups a legacy weekly series under one identity', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    foreach (['2026-07-01 18:00:00', '2026-07-08 18:00:00', '2026-07-15 18:00:00', '2026-07-22 18:00:00'] as $index => $start) {
        legacyEvent($meetup->id, $user->id, $start, '2026-06-01 10:00:0'.$index);
    }

    runGroupingMigration();

    $groups = DB::table('meetup_events')->where('meetup_id', $meetup->id)->pluck('recurrence_group');

    expect($groups)->toHaveCount(4)
        ->and($groups->filter()->unique())->toHaveCount(1)
        ->and($groups->contains(null))->toBeFalse();
});

it('groups a legacy monthly series despite uneven day gaps', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    // 31, 31, 30 Tage Abstand — ein Monatsmuster, kein fester Abstand.
    foreach (['2026-07-01 18:00:00', '2026-08-01 18:00:00', '2026-09-01 18:00:00', '2026-10-01 18:00:00'] as $index => $start) {
        legacyEvent($meetup->id, $user->id, $start, '2026-06-01 10:00:0'.$index);
    }

    runGroupingMigration();

    expect(DB::table('meetup_events')->where('meetup_id', $meetup->id)->whereNotNull('recurrence_group')->distinct()->count('recurrence_group'))->toBe(1);
});

it('keeps two independent series of the same meetup apart', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    // Der Kollisionsfall aus dem Plan: identische Konfiguration, identischer Takt,
    // identischer Ersteller — nur zu verschiedenen Zeiten angelegt.
    $first = [];
    foreach (['2026-07-01 18:00:00', '2026-07-08 18:00:00', '2026-07-15 18:00:00'] as $index => $start) {
        $first[] = legacyEvent($meetup->id, $user->id, $start, '2026-06-01 10:00:0'.$index);
    }

    $second = [];
    foreach (['2026-09-02 18:00:00', '2026-09-09 18:00:00', '2026-09-16 18:00:00'] as $index => $start) {
        $second[] = legacyEvent($meetup->id, $user->id, $start, '2026-08-15 14:30:0'.$index);
    }

    runGroupingMigration();

    $firstGroups = DB::table('meetup_events')->whereIn('id', $first)->pluck('recurrence_group');
    $secondGroups = DB::table('meetup_events')->whereIn('id', $second)->pluck('recurrence_group');

    expect($firstGroups->unique())->toHaveCount(1)
        ->and($secondGroups->unique())->toHaveCount(1)
        ->and($firstGroups->first())->not->toBeNull()
        ->and($secondGroups->first())->not->toBeNull()
        ->and($firstGroups->first())->not->toBe($secondGroups->first());
});

it('leaves irregular dates from one request ungrouped', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    // Drei Einzeltermine mit identischem Text, in einem Rutsch angelegt, ohne Takt.
    foreach (['2026-07-01 18:00:00', '2026-07-04 18:00:00', '2026-07-20 18:00:00'] as $index => $start) {
        legacyEvent($meetup->id, $user->id, $start, '2026-06-01 10:00:0'.$index);
    }

    runGroupingMigration();

    expect(DB::table('meetup_events')->where('meetup_id', $meetup->id)->whereNotNull('recurrence_group')->count())->toBe(0);
});

it('leaves a lone event ungrouped', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    legacyEvent($meetup->id, $user->id, '2026-07-01 18:00:00', '2026-06-01 10:00:00');

    runGroupingMigration();

    expect(DB::table('meetup_events')->where('meetup_id', $meetup->id)->whereNotNull('recurrence_group')->count())->toBe(0);
});

it('separates two series that differ only in their location', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    foreach (['2026-07-01 18:00:00', '2026-07-08 18:00:00'] as $index => $start) {
        legacyEvent($meetup->id, $user->id, $start, '2026-06-01 10:00:0'.$index, ['location' => 'Marktplatz']);
    }

    foreach (['2026-07-02 18:00:00', '2026-07-09 18:00:00'] as $index => $start) {
        legacyEvent($meetup->id, $user->id, $start, '2026-06-01 10:00:0'.$index, ['location' => 'Bahnhof']);
    }

    runGroupingMigration();

    expect(DB::table('meetup_events')->where('meetup_id', $meetup->id)->whereNotNull('recurrence_group')->distinct()->count('recurrence_group'))->toBe(2);
});

it('touches neither is_active nor the public change feed', function () {
    config()->set('einundzwanzig.change_log.enabled', true);

    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'is_active' => false, 'last_event_at' => null]);

    foreach (['2026-07-01 18:00:00', '2026-07-08 18:00:00', '2026-07-15 18:00:00'] as $index => $start) {
        legacyEvent($meetup->id, $user->id, $start, '2026-06-01 10:00:0'.$index);
    }

    $activeBefore = Meetup::where('is_active', true)->count();
    $changesBefore = ApiChange::count();
    $touchedBefore = DB::table('meetup_events')->where('meetup_id', $meetup->id)->pluck('updated_at')->all();

    runGroupingMigration();

    expect(Meetup::where('is_active', true)->count())->toBe($activeBefore)
        ->and($meetup->refresh()->is_active)->toBeFalse()
        ->and(ApiChange::count())->toBe($changesBefore)
        ->and(DB::table('meetup_events')->where('meetup_id', $meetup->id)->pluck('updated_at')->all())->toBe($touchedBefore);

    // Und die Migration erfindet keine Wiederholungsregel — genau das würde
    // Meetup::recalculateActivity() reihenweise auf is_active kippen lassen.
    expect(DB::table('meetup_events')->whereNotNull('recurrence_type')->count())->toBe(0)
        ->and(DB::table('meetup_events')->whereNotNull('recurrence_end_date')->count())->toBe(0);
});

it('leaves events that already carry a group alone', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $existing = MeetupEvent::factory()->count(3)->series()->create([
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
    ]);

    $groupBefore = $existing->first()->recurrence_group;

    runGroupingMigration();

    expect(MeetupEvent::whereIn('id', $existing->pluck('id'))->pluck('recurrence_group')->unique()->all())
        ->toBe([$groupBefore]);
});

it('can be rolled back', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    foreach (['2026-07-01 18:00:00', '2026-07-08 18:00:00'] as $index => $start) {
        legacyEvent($meetup->id, $user->id, $start, '2026-06-01 10:00:0'.$index);
    }

    $migration = runGroupingMigration();

    expect(DB::table('meetup_events')->whereNotNull('recurrence_group')->count())->toBe(2);

    $migration->down();

    expect(DB::table('meetup_events')->whereNotNull('recurrence_group')->count())->toBe(0);
});
