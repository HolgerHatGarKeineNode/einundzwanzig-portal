<?php

use App\Models\Country;
use App\Models\Region;

/*
 * country_or_region_route() liest config('app.domain_country')/config('app.domain_region')
 * (gesetzt von DomainMiddleware) und session('lang_country') (gesetzt vom
 * Sprachwaehler). Fuer diese Tests wird beides direkt gesetzt — das entspricht dem
 * Zustand NACH einem Middleware-Durchlauf, ohne den vollen Request/Config-Reset aus
 * DomainMiddlewareTest.php erneut durchlaufen zu muessen.
 */
beforeEach(function () {
    config(['app.domain_country' => 'us', 'app.domain_region' => 'in']);
    session(['lang_country' => 'en-US']);
});

it('builds the region variant for meetups, map, and cities (D3)', function () {
    expect(country_or_region_route('meetups.index'))
        ->toBe(route('meetups.index-region', ['country' => 'us', 'region' => 'in']))
        ->and(country_or_region_route('meetups.map'))
        ->toBe(route('meetups.map-region', ['country' => 'us', 'region' => 'in']))
        ->and(country_or_region_route('cities.index'))
        ->toBe(route('cities.index-region', ['country' => 'us', 'region' => 'in']));
});

it('falls back to the plain country route once the session language no longer matches the domain (D4, helper level)', function () {
    // Besucher auf Bitcoin Indiana schaltet die Oberflaeche auf Deutsch um. Indiana ist
    // kein deutsches Bundesland — die Region darf NICHT mitwandern.
    session(['lang_country' => 'de-DE']);

    expect(country_or_region_route('meetups.index'))
        ->toBe(route('meetups.index', ['country' => 'de']));
});

it('answers GET /de/in/meetups with 404 instead of an empty list (D4, real endpoint)', function () {
    Country::factory()->create(['code' => 'de']);

    $this->get('/de/in/meetups')->assertNotFound();
});

it('falls back to the plain country route when no region variant of it exists (D5)', function () {
    // courses.index hat bewusst keine courses.index-region — ohne die
    // Route::has()-Pruefung im Helper wuerde hier eine nicht existierende Route gebaut.
    expect(country_or_region_route('courses.index'))
        ->toBe(route('courses.index', ['country' => 'us']));
});

it('answers GET /us/in/meetups with 200 once Indiana actually exists in the DB (bonus, real endpoint)', function () {
    $usa = Country::factory()->create(['code' => 'us']);
    Region::factory()->indiana()->create(['country_id' => $usa->id]);

    $this->get('/us/in/meetups')->assertSuccessful();
});
