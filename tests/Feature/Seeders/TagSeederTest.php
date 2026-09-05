<?php

use App\Models\Tag;
use Database\Seeders\TagSeeder;

it('seeds the full curated vocabulary', function () {
    $this->seed(TagSeeder::class);

    $vocabulary = require database_path('seeders/data/tags.php');
    $expected = collect($vocabulary)->flatten(1)->count();

    expect(Tag::count())->toBe($expected)
        ->and(Tag::query()->where('type', 'meetup_event')->count())->toBe(16)
        ->and(Tag::query()->where('type', 'library_item')->count())->toBe(74)
        ->and(Tag::query()->where('type', 'course')->count())->toBe(1);
});

it('is idempotent', function () {
    $this->seed(TagSeeder::class);
    $after = Tag::count();

    $this->seed(TagSeeder::class);

    expect(Tag::count())->toBe($after);
});

it('gives every tag a name in all nine locales', function () {
    $this->seed(TagSeeder::class);

    $locales = config('einundzwanzig.tag_locales');

    Tag::all()->each(function (Tag $tag) use ($locales): void {
        foreach ($locales as $locale) {
            expect($tag->getTranslation('name', $locale, false))
                ->not->toBe('', "missing {$locale} for tag {$tag->id}");
        }
    });
});

it('uses proper nouns verbatim across locales', function () {
    $this->seed(TagSeeder::class);

    $nostr = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Nostr');

    expect($nostr)->not->toBeNull()
        ->and($nostr->getTranslation('name', 'cs'))->toBe('Nostr')
        ->and($nostr->getTranslation('name', 'pt'))->toBe('Nostr');
});

it('translates real terms per locale', function () {
    $this->seed(TagSeeder::class);

    $talk = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Vortrag');

    expect($talk->getTranslation('name', 'cs'))->toBe('Přednáška')
        ->and($talk->getTranslation('name', 'en'))->toBe('Talk')
        ->and($talk->getTranslation('name', 'pl'))->toBe('Prelekcja');
});

it('marks exactly the intended featured tags, all on events', function () {
    $this->seed(TagSeeder::class);

    $featured = Tag::query()->featured()->get();

    expect($featured)->toHaveCount(7)
        ->and($featured->pluck('type')->unique()->all())->toBe(['meetup_event']);
});

it('contains no duplicate names within a type', function () {
    $vocabulary = require database_path('seeders/data/tags.php');
    $locales = config('einundzwanzig.tag_locales');

    foreach ($vocabulary as $type => $entries) {
        $german = collect($entries)->map(
            fn (array $e): string => is_string($e['name']) ? $e['name'] : $e['name']['de']
        );

        expect($german->duplicates()->all())->toBe([], "duplicate German name in type {$type}");

        foreach ($entries as $e) {
            if (is_array($e['name'])) {
                // Compare the set, not the order: the data file groups German first
                // as the source language, the config lists locales alphabetically.
                $keys = array_keys($e['name']);
                sort($keys);
                $expected = $locales;
                sort($expected);

                expect($keys)->toBe($expected, 'locale set mismatch for '.$e['name']['de']);
            }
        }
    }
});

it('generates a slug per locale', function () {
    $this->seed(TagSeeder::class);

    $tag = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Selbstverwahrung');

    expect($tag->getTranslation('slug', 'de'))->toBe('selbstverwahrung')
        ->and($tag->getTranslation('slug', 'cs'))->toBe('vlastni-uschova');
});

it('marks every seeded tag as approved', function () {
    $this->seed(TagSeeder::class);

    expect(Tag::query()->pending()->count())->toBe(0);
});
