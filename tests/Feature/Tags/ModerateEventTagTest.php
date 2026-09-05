<?php

use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Livewire\Livewire;

/**
 * Issue #68: the event difficulty scale had a beginner end and an expert end and
 * nothing in between.
 *
 * The ordering assertions read the picker's RENDERED rows rather than the seeder file,
 * because those are two different orders: the file's position becomes `order_column`,
 * but the picker lifts the whole featured block above the rest before it renders, and
 * "Einsteiger" is featured while the other two are not. What the organiser sees is
 * therefore the only order worth asserting.
 */
beforeEach(function () {
    $this->seed(TagSeeder::class);
});

/**
 * The rendered rows of the event picker, in DOM order, by their German name.
 *
 * @return array<int, string>
 */
function renderedEventTagNames(): array
{
    $html = Livewire::test('tags.picker', ['type' => 'meetup_event'])->html();

    preg_match_all('/data-testid="tag-option-(\d+)"/', $html, $matches);

    return collect($matches[1])
        ->map(fn (string $id): string => (string) Tag::findOrFail((int) $id)->getTranslation('name', 'de', false))
        ->all();
}

it('adds Moderate to the meetup_event vocabulary in every locale', function () {
    $moderate = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $tag): bool => $tag->getTranslation('name', 'de', false) === 'Mittelstufe');

    expect($moderate)->not->toBeNull()
        ->and($moderate->isApproved())->toBeTrue()
        ->and($moderate->getTranslation('name', 'en'))->toBe('Moderate')
        ->and($moderate->getTranslation('name', 'cs'))->toBe('Mírně pokročilí')
        ->and($moderate->getTranslation('name', 'pl'))->toBe('Średniozaawansowani')
        ->and($moderate->getTranslation('slug', 'en'))->toBe('moderate');

    foreach (config('einundzwanzig.tag_locales') as $locale) {
        expect($moderate->getTranslation('name', $locale, false))
            ->not->toBe('', "missing {$locale} for the Moderate tag");
    }
});

it('renders Moderate between Beginners and Advanced, not at the end of the picker', function () {
    actingAsUser();

    $names = renderedEventTagNames();

    $beginners = array_search('Einsteiger', $names, true);
    $moderate = array_search('Mittelstufe', $names, true);
    $advanced = array_search('Fortgeschrittene', $names, true);

    expect($names)->toHaveCount(16)
        ->and($beginners)->not->toBeFalse()
        ->and($moderate)->not->toBeFalse()
        ->and($advanced)->not->toBeFalse();

    expect($moderate)->toBeGreaterThan($beginners)
        ->and($moderate)->toBeLessThan($advanced)
        // The scale must read as a scale: the row is somewhere in the middle of the
        // list, not appended behind everything else.
        ->and($moderate)->toBeLessThan(count($names) - 1);
});

it('offers Moderate the same way as Advanced: selectable, not in the resting list', function () {
    actingAsUser();

    $options = Livewire::test('tags.picker', ['type' => 'meetup_event'])->instance()->options;

    $moderate = $options->first(fn (Tag $tag): bool => $tag->getTranslation('name', 'de', false) === 'Mittelstufe');
    $advanced = $options->first(fn (Tag $tag): bool => $tag->getTranslation('name', 'de', false) === 'Fortgeschrittene');

    expect($moderate)->not->toBeNull()
        ->and($moderate->featured)->toBe($advanced->featured)
        ->and($moderate->featured)->toBeFalse();
});
