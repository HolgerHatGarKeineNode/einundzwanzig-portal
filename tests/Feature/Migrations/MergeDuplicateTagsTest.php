<?php

use App\Models\LibraryItem;
use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Runs the data migration a second time, against a set that reproduces the duplicates
 * found in production. RefreshDatabase already ran it once on an empty database, which
 * is itself the "nothing to do" case.
 */
function runMerge(): void
{
    $migration = require database_path('migrations/2026_08_17_153012_merge_duplicate_tags.php');
    $migration->up();
}

function tagNamed(string $locale, string $name, string $type = 'library_item'): Tag
{
    $tag = new Tag(['type' => $type]);
    $tag->setTranslation('name', $locale, $name);
    $tag->approved_at = now();
    $tag->save();

    return $tag;
}

it('does nothing on a database without the duplicates', function () {
    $untouched = tagNamed('de', 'Etwas völlig anderes');

    runMerge();

    expect(Tag::count())->toBe(1)
        ->and($untouched->fresh()->getTranslation('name', 'de'))->toBe('Etwas völlig anderes');
});

it('folds the english twin into the german tag and moves its items', function () {
    $keep = tagNamed('de', 'Philosophie');
    $fold = tagNamed('en', 'Philosophie');

    $itemA = LibraryItem::factory()->create();
    $itemB = LibraryItem::factory()->create();
    $itemA->attachTag($keep);
    $itemB->attachTag($fold);

    runMerge();

    expect(Tag::find($fold->id))->toBeNull()
        ->and(Tag::find($keep->id))->not->toBeNull();

    expect(DB::table('taggables')->where('tag_id', $keep->id)->count())->toBe(2);
    expect($itemB->fresh()->tags->pluck('id')->all())->toBe([$keep->id]);
});

it('drops the pivot instead of failing when an item carries both tags', function () {
    // The unique index on (tag_id, taggable_id, taggable_type) makes a plain UPDATE
    // fail here. The association must survive through the surviving tag exactly once.
    $keep = tagNamed('de', 'Philosophie');
    $fold = tagNamed('en', 'Philosophie');

    $item = LibraryItem::factory()->create();
    $item->attachTag($keep);
    $item->attachTag($fold);

    expect(DB::table('taggables')->where('taggable_id', $item->id)->count())->toBe(2);

    runMerge();

    expect(DB::table('taggables')->where('taggable_id', $item->id)->count())->toBe(1)
        ->and($item->fresh()->tags->pluck('id')->all())->toBe([$keep->id]);
});

it('removes the typo and the stray-hash variant', function () {
    $immo = tagNamed('de', 'Immobilien');
    $typo = tagNamed('de', 'Immmobilien');
    $bindle = tagNamed('de', 'Bindle');
    $hash = tagNamed('de', '#Bindle ');

    runMerge();

    expect(Tag::find($typo->id))->toBeNull()
        ->and(Tag::find($hash->id))->toBeNull()
        ->and(Tag::find($immo->id))->not->toBeNull()
        ->and(Tag::find($bindle->id))->not->toBeNull();
});

it('folds three thesis names into one', function () {
    $keep = tagNamed('de', 'Abschlussarbeit');
    $a = tagNamed('de', 'wissenschaftliche Arbeit');
    $b = tagNamed('de', 'Masterarbeit');
    $c = tagNamed('de', 'Bachelorarbeit');

    foreach ([$a, $b, $c] as $tag) {
        LibraryItem::factory()->create()->attachTag($tag);
    }

    runMerge();

    expect(Tag::whereIn('id', [$a->id, $b->id, $c->id])->count())->toBe(0)
        ->and(DB::table('taggables')->where('tag_id', $keep->id)->count())->toBe(3);
});

it('renames spellings the vocabulary normalises', function () {
    $anlage = tagNamed('de', 'Anlage');
    $music = tagNamed('de', 'Music');
    $community = tagNamed('de', 'community');

    runMerge();

    expect($anlage->fresh()->getTranslation('name', 'de'))->toBe('Geldanlage')
        ->and($music->fresh()->getTranslation('name', 'de'))->toBe('Musik')
        ->and($community->fresh()->getTranslation('name', 'de'))->toBe('Gemeinschaft');
});

it('merges Privatsphäre into Privacy and then renames it back', function () {
    // Order matters: the merge removes the German duplicate, the rename then gives the
    // survivor the German name the vocabulary expects.
    $privacy = tagNamed('de', 'Privacy');
    $privat = tagNamed('de', 'Privatsphäre');

    LibraryItem::factory()->create()->attachTag($privat);

    runMerge();

    expect(Tag::find($privat->id))->toBeNull()
        ->and($privacy->fresh()->getTranslation('name', 'de'))->toBe('Privatsphäre')
        ->and(DB::table('taggables')->where('tag_id', $privacy->id)->count())->toBe(1);
});

it('is idempotent', function () {
    tagNamed('de', 'Philosophie');
    tagNamed('en', 'Philosophie');
    tagNamed('de', 'Anlage');

    runMerge();
    $after = Tag::query()->get()->map->getTranslations('name')->toArray();

    runMerge();

    expect(Tag::query()->get()->map->getTranslations('name')->toArray())->toBe($after);
});

it('leaves the seeder able to match every renamed tag', function () {
    // The point of the renames: TagSeeder matches on the German name. If a rename were
    // missing, the seeder would create a second row instead of translating the first.
    tagNamed('de', 'Anlage');
    tagNamed('de', 'Music');
    tagNamed('de', 'Africa');

    runMerge();
    $this->seed(TagSeeder::class);

    $german = Tag::query()->where('type', 'library_item')->get()
        ->map(fn (Tag $t): string => $t->getTranslation('name', 'de'));

    expect($german->duplicates()->all())->toBe([]);
});

it('reduces the real production set from 89 to 75 and leaves it seedable', function () {
    foreach (require base_path('tests/Fixtures/production-tags-2026-08-17.php') as [$type, $locale, $name]) {
        tagNamed($locale, $name, $type);
    }

    expect(Tag::count())->toBe(89);

    runMerge();

    // 14 rows collapse — the count named in the curation record.
    expect(Tag::count())->toBe(75);

    // And afterwards every German name is unique per type, which is exactly what
    // TagSeeder needs to translate in place instead of creating second rows.
    Tag::query()->get()->groupBy('type')->each(function ($tags, $type): void {
        $german = $tags->map(fn (Tag $t): string => $t->getTranslation('name', 'de', false));
        expect($german->duplicates()->all())->toBe([], "duplicate German name left in {$type}");
    });

    $this->seed(TagSeeder::class);

    // The seeder adds the 15 event tags on top; nothing gets duplicated.
    expect(Tag::query()->where('type', 'meetup_event')->count())->toBe(15)
        ->and(Tag::query()->where('type', 'library_item')->count())->toBe(74);
});
