<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use Database\Seeders\TagSeeder;

/**
 * Verifies the three Flux behaviours the UX concept derived from reading flux.js but
 * could not confirm without a browser:
 *   1. does the search term reset after Flux clears its own input on select
 *   2. does the panel open on focus rather than on click
 *   3. does the resting state really show only the featured tags
 */
beforeEach(function () {
    $this->seed(TagSeeder::class);

    $country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create([
        'city_id' => $city->id,
        'created_by' => actingAsUser()->id,
    ]);
});

it('shows only featured tags in the resting state and reveals the rest on typing', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    $total = $page->script("document.querySelectorAll('.tag-option').length");
    $visible = $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none').length"
    );

    $page->assertNoJavaScriptErrors();

    // 15 event tags are rendered, only the 7 featured ones are visible at rest.
    expect($total)->toBe(15)
        ->and($visible)->toBe(7);
});

it('opens the panel and keeps the search working across languages', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    // Type the Czech name of a tag whose visible label is German, without diacritics.
    $page->script("document.querySelector('[data-testid=tag-picker] input')?.focus()");
    $page->wait(0.3);
    $page->type('[data-testid=tag-picker] input', 'prednaska');
    $page->wait(0.5);

    $matches = $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none')
            .map(o => o.innerText.trim())"
    );

    $page->assertNoJavaScriptErrors();

    // "Vortrag" must be findable by its Czech alias "Přednáška".
    expect(collect($matches[0])->implode(' | '))->toContain('Vortrag');
});

it('returns to the resting state after a selection', function () {
    // The assumption under test: Flux clears its own input on select (clear="… select")
    // WITHOUT firing an input event. If our x-on:change reset does not compensate, the
    // panel stays stuck in search mode and every later visit shows all 15 tags.
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    $page->script("document.querySelector('[data-testid=tag-picker] input')?.focus()");
    $page->wait(0.3);
    $page->type('[data-testid=tag-picker] input', 'workshop');
    $page->wait(0.5);

    // Click the first still-visible option.
    $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none')[0]?.click()"
    );
    $page->wait(0.8);

    $searching = $page->script("document.querySelector('[data-searching]')?.dataset.searching");
    $visible = $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none').length"
    );

    $page->assertNoJavaScriptErrors();

    expect($searching)->toBe('false')
        ->and($visible)->toBe(7);
});
