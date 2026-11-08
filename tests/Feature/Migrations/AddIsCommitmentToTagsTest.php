<?php

use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The is_commitment column (issue #149): a promise flag on the tag row, seeded on
 * Beginners and Families.
 *
 * RefreshDatabase already ran the migration on the empty test database, so what is
 * left to pin is the column's contract: it exists, it defaults to false so every
 * pre-existing row stays a plain label until the seeder (or a command on production)
 * says otherwise, and it casts to a real boolean rather than leaking 0/1 integers
 * into the templates that branch on it.
 */
function isCommitmentMigration(): object
{
    return require database_path('migrations/2026_09_18_120000_add_is_commitment_to_tags_table.php');
}

it('adds a boolean column defaulting to false', function () {
    $tag = Tag::factory()->create();

    $column = collect(DB::select('PRAGMA table_info(tags)'))
        ->first(fn (object $c): bool => $c->name === 'is_commitment');

    expect($column)->not->toBeNull()
        ->and($column->type)->toBeIn(['boolean', 'tinyint(1)', 'integer'])
        ->and((int) $column->dflt_value)->toBe(0)
        ->and($tag->fresh()->is_commitment)->toBeFalse();
});

it('casts the flag to a boolean', function () {
    Tag::factory()->create();
    $tag = Tag::query()->first();

    $tag->newQuery()->whereKey($tag->id)->update(['is_commitment' => 1]);

    expect($tag->fresh()->is_commitment)->toBeTrue();
});

it('marks exactly Beginners and Families in the seeded vocabulary', function () {
    $this->seed(TagSeeder::class);

    $commitments = Tag::query()
        ->where('is_commitment', true)
        ->get()
        ->map(fn (Tag $tag): string => (string) $tag->getTranslation('name', 'de', false))
        ->sort()
        ->values()
        ->all();

    expect($commitments)->toBe(['Einsteiger', 'Familien']);
});

it('keeps the flag idempotent across repeated seeding', function () {
    $this->seed(TagSeeder::class);

    // An editor could not flip this flag through the UI — but a moderator CAN
    // change other fields, and the seeder runs again on every environment
    // rebuild. The flag comes from the vocabulary file unconditionally, like
    // `featured`, so re-seeding must not drift.
    $this->seed(TagSeeder::class);

    expect(Tag::query()->where('is_commitment', true)->count())->toBe(2);
});

it('drops the column again on down', function () {
    isCommitmentMigration()->down();

    expect(Schema::hasColumn('tags', 'is_commitment'))->toBeFalse();

    // And back up, so the suite's own database is left in the migrated state
    // for whichever test runs after this one.
    isCommitmentMigration()->up();

    expect(Schema::hasColumn('tags', 'is_commitment'))->toBeTrue();
});
