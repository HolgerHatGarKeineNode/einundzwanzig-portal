<?php

/*
|--------------------------------------------------------------------------
| Migration 2026_09_17_200000 — NIP-52 publishing default-on
|--------------------------------------------------------------------------
|
| RefreshDatabase runs the migration against an EMPTY table, which proves nothing
| about the backfill. So the tests below first put rows into the pre-migration
| state (switched off, default false) by calling down() and writing the rows, and
| only then call up() against them.
|
*/

use App\Models\Meetup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function enableNostrPublishingMigration(): object
{
    return require base_path('database/migrations/2026_09_17_200000_enable_nostr_publishing_by_default_on_meetups_table.php');
}

/**
 * A meetup row written WITHOUT the column, so the value comes from the database
 * default and not from the model or the factory.
 */
function meetupRowWithoutPublishingFlag(string $name): bool
{
    $template = Meetup::factory()->make(['name' => $name])->getAttributes();
    unset($template['nostr_publishing_enabled']);

    DB::table('meetups')->insert(array_merge($template, ['created_at' => now(), 'updated_at' => now()]));

    return (bool) DB::table('meetups')->where('name', $name)->value('nostr_publishing_enabled');
}

it('gives a new meetup nostr publishing by default', function () {
    expect(meetupRowWithoutPublishingFlag('Default nach Migration'))->toBeTrue();
});

it('switches publishing on for every existing meetup', function () {
    enableNostrPublishingMigration()->down();

    $optedOut = Meetup::factory()->create(['nostr_publishing_enabled' => false]);
    $optedIn = Meetup::factory()->create(['nostr_publishing_enabled' => true]);

    // Positive control: the pre-migration state really holds a switched-off row.
    expect((bool) DB::table('meetups')->where('id', $optedOut->id)->value('nostr_publishing_enabled'))->toBeFalse();

    enableNostrPublishingMigration()->up();

    expect($optedOut->fresh()->nostr_publishing_enabled)->toBeTrue()
        ->and($optedIn->fresh()->nostr_publishing_enabled)->toBeTrue();
});

it('restores the false default on rollback without guessing per-row values', function () {
    $existing = Meetup::factory()->create();

    enableNostrPublishingMigration()->down();

    expect(meetupRowWithoutPublishingFlag('Default nach Rollback'))->toBeFalse()
        ->and($existing->fresh()->nostr_publishing_enabled)->toBeTrue();
});

/*
 * On SQLite `->change()` rebuilds the table, and the rebuild cannot re-create the
 * expression index on lower(name). The migration drops and re-creates it; this checks
 * that it is still there and still enforces case-insensitive uniqueness, in both
 * directions.
 */
it('keeps the case-insensitive unique index on meetup names through up and down', function () {
    $migration = enableNostrPublishingMigration();

    $migration->down();
    expect(Schema::hasIndex('meetups', 'meetups_lower_name_unique'))->toBeTrue();

    $migration->up();
    expect(Schema::hasIndex('meetups', 'meetups_lower_name_unique'))->toBeTrue();

    meetupRowWithoutPublishingFlag('Einundzwanzig Indexprobe');

    expect(fn () => meetupRowWithoutPublishingFlag('EINUNDZWANZIG INDEXPROBE'))
        ->toThrow(QueryException::class);
});
