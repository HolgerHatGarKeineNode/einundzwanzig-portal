<?php

use App\Models\Country;
use App\Models\Region;

/*
 * P5 (docs/plans/2026-08-24T1738-region-persistenz-navigation.md, app/helpers.php
 * active_region_for()): der WAECHTER, ohne den ein Laenderwechsel auf einer Regionsseite
 * wieder im 404 landet, den P2 gerade geschlossen hat. Die Region der laufenden Route gilt
 * nur, wenn das Ziel-Land dem Land dieser Route entspricht — sonst entstuende auf
 * /us/nc/cities beim Sprung nach Deutschland ein /de/nc/….
 *
 * request()->route()/request()->route('country') bleiben nach $this->get() ueber das
 * Testende hinaus im Container gebunden (empirisch mit pest --agent geprueft, wie schon im
 * P2-Bericht: "Testweg setzt currentRouteName/currentRouteParams direkt, weil
 * Livewire::test() die aeussere Route nicht sieht" — hier reicht der echte Request, weil
 * kein Livewire-Roundtrip noetig ist).
 */
beforeEach(function () {
    $this->usa = Country::factory()->create(['code' => 'us']);
    Region::factory()->indiana()->create(['country_id' => $this->usa->id]);
    $this->nc = Region::factory()->create(['country_id' => $this->usa->id, 'code' => 'nc', 'name' => 'North Carolina']);
    Country::factory()->create(['code' => 'de']);
});

it('drops the region once the target country no longer matches the running route (N3)', function () {
    test()->get('http://portal.einundzwanzig.space/us/nc/cities')->assertOk();

    expect(active_region_for('de'))->toBeNull()
        ->and(country_or_region_route('meetups.index', ['country' => 'de']))
        ->toBe(route('meetups.index', ['country' => 'de']))
        ->not->toBe(route('meetups.index-region', ['country' => 'de', 'region' => 'nc']));
});

it('keeps the region for the matching country itself (Gegenprobe zum Waechter)', function () {
    test()->get('http://portal.einundzwanzig.space/us/nc/cities')->assertOk();

    expect(active_region_for('us'))->toBe('nc');
});

it('compares the country case-insensitively instead of silently dropping the region (N3, Gross-/Kleinschreibung)', function () {
    // Der Routen-Parameter traegt "US" genau so, wie er in der URL stand — die Region
    // darf trotzdem nicht verloren gehen, nur weil ein Aufrufer 'us' klein uebergibt.
    Country::query()->where('code', 'us')->update(['code' => 'US']);

    test()->get('http://portal.einundzwanzig.space/US/nc/cities')->assertOk();

    expect(request()->route()?->parameter('country'))->toBe('US')
        ->and(active_region_for('us'))->toBe('nc')
        ->and(country_or_region_route('cities.index', ['country' => 'us']))
        ->toBe(route('cities.index-region', ['country' => 'us', 'region' => 'nc']));
});
