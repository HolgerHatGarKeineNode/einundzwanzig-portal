<?php

use App\Models\Country;

/**
 * Issue #148: the meetup add-city modal must not expose WGS84 number fields.
 * Nominatim search is faked in Livewire feature tests; this file only proves
 * the modal the organiser sees has a search field and no coordinate inputs.
 */
it('opens the meetup-create add-city modal without latitude or longitude number fields', function () {
    Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    actingAsUser();

    $page = visit('/de/meetup-create');

    $page->click(__('Stadt hinzufügen'));

    $page->assertVisible('[data-testid="add-city-query"]')
        ->assertVisible('[data-testid="add-city-search"]')
        ->assertNoJavaScriptErrors();

    $probe = $page->script(<<<'JS'
        (() => {
            const query = document.querySelector('[data-testid="add-city-query"]');
            const search = document.querySelector('[data-testid="add-city-search"]');
            const country = document.querySelector('[data-testid="add-city-country"]');
            const modal = query?.closest('[data-flux-modal], dialog, [role="dialog"]') ?? query?.parentElement?.parentElement;
            const root = modal ?? document;
            const numberInputs = [...root.querySelectorAll('input[type="number"]')];
            const coordinateNumbers = numberInputs.filter((el) => {
                const haystack = [
                    el.getAttribute('name') ?? '',
                    el.getAttribute('wire:model') ?? '',
                    el.id ?? '',
                ].join(' ');

                return /lat|lon|latitude|longitude/i.test(haystack);
            });
            const wiredCoordinates = document.querySelectorAll(
                '[wire\\:model="newCityLatitude"], [wire\\:model="newCityLongitude"]'
            ).length;

            return {
                hasQuery: query !== null,
                hasSearch: search !== null,
                hasCountry: country !== null,
                coordinateNumberCount: coordinateNumbers.length,
                wiredCoordinateCount: wiredCoordinates,
            };
        })()
    JS);

    expect($probe['hasQuery'])->toBeTrue()
        ->and($probe['hasSearch'])->toBeTrue()
        ->and($probe['hasCountry'])->toBeTrue()
        ->and($probe['coordinateNumberCount'])->toBe(0)
        ->and($probe['wiredCoordinateCount'])->toBe(0);
});
