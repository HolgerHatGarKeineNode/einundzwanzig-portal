<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The data migration that adds the "Mittelstufe" / "Moderate" event tag to a database
 * that was seeded before it existed (issue #68).
 *
 * RefreshDatabase already ran it once, on an empty database — that is the "nothing to
 * do" case. The state it is really meant for is reproduced by seeding the current
 * vocabulary and then removing the tag again through the migration's own down(), which
 * is exactly the shape of a database seeded before this change.
 */
function moderateTagMigration(): object
{
    return require database_path('migrations/2026_09_05_002216_add_moderate_meetup_event_tag.php');
}

function meetupEventTagNamed(string $german): ?Tag
{
    return Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $tag): bool => $tag->getTranslation('name', 'de', false) === $german);
}

/**
 * Every tag, in the order `ordered()` returns them, by German name.
 *
 * @return array<int, string>
 */
function tagLadder(): array
{
    return Tag::query()->ordered()->get()
        ->map(fn (Tag $tag): string => (string) $tag->getTranslation('name', 'de', false))
        ->all();
}

it('does nothing on a database without the event vocabulary', function () {
    moderateTagMigration()->up();

    expect(Tag::count())->toBe(0);
});

it('slots the tag into the ladder between Beginners and Advanced', function () {
    $this->seed(TagSeeder::class);
    $seeded = tagLadder();

    // Back to the state of a database seeded before this change.
    moderateTagMigration()->down();

    expect(meetupEventTagNamed('Mittelstufe'))->toBeNull()
        ->and(tagLadder())->not->toContain('Mittelstufe');

    moderateTagMigration()->up();

    $ladder = tagLadder();
    $moderate = array_search('Mittelstufe', $ladder, true);

    expect($moderate)->not->toBeFalse()
        ->and($moderate)->toBeGreaterThan(array_search('Einsteiger', $ladder, true))
        ->and($moderate)->toBeLessThan(array_search('Fortgeschrittene', $ladder, true))
        ->and($moderate)->toBeLessThan(count($ladder) - 1);

    // Same sequence as a fresh seed produces, so the migrated database and a newly
    // seeded one offer the vocabulary in one and the same order.
    expect($ladder)->toBe($seeded);
});

it('creates the tag exactly as the seeder vocabulary describes it', function () {
    $this->seed(TagSeeder::class);

    moderateTagMigration()->down();
    moderateTagMigration()->up();

    $vocabulary = require database_path('seeders/data/tags.php');

    $expected = collect($vocabulary['meetup_event'])
        ->first(fn (array $each): bool => is_array($each['name']) && $each['name']['de'] === 'Mittelstufe');

    $tag = meetupEventTagNamed('Mittelstufe');

    expect($expected)->not->toBeNull()
        ->and($tag->icon)->toBe($expected['icon'])
        ->and($tag->featured)->toBe((bool) $expected['featured']);

    foreach ($expected['name'] as $locale => $name) {
        expect($tag->getTranslation('name', $locale, false))->toBe($name, "locale {$locale}");
    }
});

it('is idempotent', function () {
    $this->seed(TagSeeder::class);
    $ladder = tagLadder();

    moderateTagMigration()->up();
    moderateTagMigration()->up();

    expect(Tag::query()->where('type', 'meetup_event')->count())->toBe(16)
        ->and(tagLadder())->toBe($ladder);
});

it('leaves the tags of existing events untouched', function () {
    $this->seed(TagSeeder::class);

    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id]);
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);

    $event->attachTag(meetupEventTagNamed('Vortrag'));
    $event->attachTag(meetupEventTagNamed('Einsteiger'));

    $taggables = DB::table('taggables')->orderBy('tag_id')->get()->toArray();
    $tagIds = $event->fresh()->tags->pluck('id')->sort()->values()->all();

    moderateTagMigration()->down();
    moderateTagMigration()->up();

    expect(DB::table('taggables')->orderBy('tag_id')->get()->toArray())->toEqual($taggables)
        ->and($event->fresh()->tags->pluck('id')->sort()->values()->all())->toBe($tagIds);
});

it('keeps a tag that an event already uses when rolled back', function () {
    $this->seed(TagSeeder::class);

    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id]);
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);

    $moderate = meetupEventTagNamed('Mittelstufe');
    $event->attachTag($moderate);

    moderateTagMigration()->down();

    expect(meetupEventTagNamed('Mittelstufe'))->not->toBeNull()
        ->and($event->fresh()->tags->pluck('id')->all())->toBe([$moderate->id]);
});
