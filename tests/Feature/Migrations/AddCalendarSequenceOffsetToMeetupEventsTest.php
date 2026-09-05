<?php

/*
|--------------------------------------------------------------------------
| Issue #97 — Migration 2026_09_05_194428
|--------------------------------------------------------------------------
|
| The one thing this migration must not get wrong is the row that is ALREADY
| cancelled when it runs. Its subscribers hold SEQUENCE `updated_at + 1`, the
| number #56 emitted, and RFC 5546 §2.1.5 lets a client ignore anything lower
| ("the component with the highest numeric value for the SEQUENCE property
| obsoletes all other revisions of the component with lower values"). A default
| of 0 on those rows would therefore not merely look untidy — it would put the
| next revision of every cancelled event below the copy already delivered.
|
| RefreshDatabase runs the migration during setup, but against an EMPTY table,
| and a green run over nothing proves nothing about a backfill. So each test
| here puts the table back into its pre-#97 shape (no column at all), seeds rows
| through the normal factory, and only then calls up() against them — the same
| approach the AddLinksToMeetupEvents test next door takes.
|
*/

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\Schema;

function runAddCalendarSequenceOffsetMigration(): void
{
    (require base_path('database/migrations/2026_09_05_194428_add_calendar_sequence_offset_to_meetup_events_table.php'))->up();
}

/**
 * Rows in the pre-#97 shape: one cancelled, one not, and no offset column.
 *
 * @return array<string, int>
 */
function seedPreNinetySevenMeetupEvents(): array
{
    $meetup = Meetup::factory()->create();

    $ids = [
        'cancelled' => MeetupEvent::factory()->for($meetup)->create([
            'start' => now()->addWeek(),
            'cancelled_at' => now(),
        ])->id,
        'confirmed' => MeetupEvent::factory()->for($meetup)->create([
            'start' => now()->addWeek(),
        ])->id,
    ];

    // Dropping the column takes the values the model hook wrote with it, so
    // whatever the assertions find afterwards came from the migration.
    Schema::table('meetup_events', fn ($table) => $table->dropColumn('calendar_sequence_offset'));

    return $ids;
}

it('starts an already-cancelled row at the offset its subscribers were served', function () {
    $ids = seedPreNinetySevenMeetupEvents();

    expect(Schema::hasColumn('meetup_events', 'calendar_sequence_offset'))
        ->toBeFalse('the pre-#97 shape was not restored');

    runAddCalendarSequenceOffsetMigration();

    expect(MeetupEvent::findOrFail($ids['cancelled'])->calendarSequenceOffset())->toBe(1)
        ->and(MeetupEvent::findOrFail($ids['confirmed'])->calendarSequenceOffset())->toBe(0);
});

it('emits the same SEQUENCE for a backfilled row as the feed did before the migration', function () {
    $ids = seedPreNinetySevenMeetupEvents();

    // What #56's feed produced for a cancelled row — `updated_at` plus the
    // hard-wired one — read off the row itself so the expectation cannot drift
    // with the fixture.
    $expected = MeetupEvent::findOrFail($ids['cancelled'])->updated_at->getTimestamp() + 1;

    runAddCalendarSequenceOffsetMigration();

    $event = MeetupEvent::findOrFail($ids['cancelled']);

    expect($event->updated_at->getTimestamp() + $event->calendarSequenceOffset())->toBe($expected);
});
