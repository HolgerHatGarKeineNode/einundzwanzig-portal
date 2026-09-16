<?php

use App\Models\Country;

/**
 * City create must not expose WGS84 number fields or a mappr.co iframe.
 * Nominatim search is faked in Livewire feature tests; this file only proves
 * the page the user sees has the OSM picker and no coordinate inputs.
 */
it('renders city-create without latitude or longitude number fields and without mappr.co', function () {
    Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    actingAsUser();

    $page = visit('/de/city-create');

    $page->assertVisible('[data-testid="osm-query"]')
        ->assertVisible('[data-testid="osm-search"]')
        ->assertNoJavaScriptErrors();

    $probe = $page->script(<<<'JS'
        (() => {
            const query = document.querySelector('[data-testid="osm-query"]');
            const search = document.querySelector('[data-testid="osm-search"]');
            const html = document.documentElement.innerHTML;
            const numberInputs = [...document.querySelectorAll('input[type="number"]')];
            const coordinateNumbers = numberInputs.filter((el) => {
                const haystack = [
                    el.getAttribute('name') ?? '',
                    el.getAttribute('wire:model') ?? '',
                    el.id ?? '',
                ].join(' ');

                return /\b(lat|lon|latitude|longitude|breitengrad|längengrad)\b/i.test(haystack);
            });
            const wiredCoordinates = document.querySelectorAll(
                '[wire\\:model="latitude"], [wire\\:model="longitude"]'
            ).length;

            return {
                hasQuery: query !== null,
                hasSearch: search !== null,
                coordinateNumberCount: coordinateNumbers.length,
                wiredCoordinateCount: wiredCoordinates,
                hasMappr: html.includes('mappr.co'),
            };
        })()
    JS);

    expect($probe['hasQuery'])->toBeTrue()
        ->and($probe['hasSearch'])->toBeTrue()
        ->and($probe['coordinateNumberCount'])->toBe(0)
        ->and($probe['wiredCoordinateCount'])->toBe(0)
        ->and($probe['hasMappr'])->toBeFalse();
});
