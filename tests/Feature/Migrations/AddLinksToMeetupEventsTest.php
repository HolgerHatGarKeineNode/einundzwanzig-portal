<?php

/*
|--------------------------------------------------------------------------
| Issue #70 — Migration 2026_09_05_030941
|--------------------------------------------------------------------------
|
| The one thing this migration must not get wrong: an organiser's existing
| link has to be there afterwards, as the first entry of the new list.
|
| RefreshDatabase runs the migration during test setup, but against an EMPTY
| meetup_events table — a green run over nothing proves nothing about a data
| migration. So every test here first puts the table back into the shape
| production had before this migration (the `links` column simply is not
| there), seeds rows through the normal factory, and only then calls the
| migration's up() against those rows. That is the same approach the
| NormaliseCityNames migration test takes next door, plus the dropColumn()
| step, because this migration adds a column and would otherwise refuse to
| run twice.
|
*/

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function runAddLinksToMeetupEventsMigration(): void
{
    (require base_path('database/migrations/2026_09_05_030941_add_links_to_meetup_events_table.php'))->up();
}

/**
 * Rows in the pre-#70 shape: `link` filled or empty, and no `links` column at all.
 *
 * @return array<string, int>
 */
function seedPreSeventyMeetupEvents(): array
{
    $meetup = Meetup::factory()->create();

    $ids = [
        'with_link' => MeetupEvent::factory()->for($meetup)->create([
            'link' => 'https://www.meetup.com/bitcoin-berlin/events/123456789/',
        ])->id,
        'without_link' => MeetupEvent::factory()->for($meetup)->create(['link' => null])->id,
        'empty_link' => MeetupEvent::factory()->for($meetup)->create(['link' => ''])->id,
    ];

    Schema::table('meetup_events', fn ($table) => $table->dropColumn('links'));

    return $ids;
}

it('carries every existing link over as the first entry of the new list', function () {
    $ids = seedPreSeventyMeetupEvents();

    expect(Schema::hasColumn('meetup_events', 'links'))->toBeFalse('the old shape was not restored')
        ->and(DB::table('meetup_events')->where('id', $ids['with_link'])->value('link'))
        ->toBe('https://www.meetup.com/bitcoin-berlin/events/123456789/');

    runAddLinksToMeetupEventsMigration();

    $event = MeetupEvent::findOrFail($ids['with_link']);

    expect($event->links)->toBe([['url' => 'https://www.meetup.com/bitcoin-berlin/events/123456789/']])
        ->and($event->linkList())->toBe([
            ['url' => 'https://www.meetup.com/bitcoin-berlin/events/123456789/', 'label' => null],
        ])
        // The deprecated column is untouched by the migration: it stays the mirror of
        // the first entry, which is what the ICS feed and the MCP tools still read.
        ->and($event->link)->toBe('https://www.meetup.com/bitcoin-berlin/events/123456789/');
});

it('leaves a linkless row NULL rather than writing an empty list', function () {
    $ids = seedPreSeventyMeetupEvents();

    runAddLinksToMeetupEventsMigration();

    // NULL and [] are not the same on this column: NULL is "never written in the new
    // shape", [] is "the organiser removed every link" (see MeetupEvent::linkList()).
    expect(DB::table('meetup_events')->where('id', $ids['without_link'])->value('links'))->toBeNull()
        ->and(DB::table('meetup_events')->where('id', $ids['empty_link'])->value('links'))->toBeNull()
        ->and(MeetupEvent::findOrFail($ids['without_link'])->linkList())->toBe([]);
});

it('stores the backfilled value in the same encoding the model writes', function () {
    $ids = seedPreSeventyMeetupEvents();

    runAddLinksToMeetupEventsMigration();

    $backfilled = DB::table('meetup_events')->where('id', $ids['with_link'])->value('links');

    // Byte-for-byte, not merely equivalent after decoding: a row the migration wrote
    // and a row the model wrote must not differ in their escaping.
    $throughModel = MeetupEvent::findOrFail($ids['without_link']);
    $throughModel->update(['links' => [['url' => 'https://www.meetup.com/bitcoin-berlin/events/123456789/']]]);

    expect($backfilled)
        ->toBe(DB::table('meetup_events')->where('id', $throughModel->id)->value('links'));
});

it('does not touch a row that already carries a list when it runs again', function () {
    $ids = seedPreSeventyMeetupEvents();

    runAddLinksToMeetupEventsMigration();

    $event = MeetupEvent::findOrFail($ids['with_link']);
    $event->update(['links' => [
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc'],
    ]]);

    runAddLinksToMeetupEventsMigration();

    expect($event->refresh()->links)->toBe([
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc'],
    ]);
});
