<?php

use Livewire\Livewire;

it('rejects non-string updates to currentCountry to prevent TypeError on string-typed property', function () {
    Livewire::test('country.chooser')
        ->set('currentCountry', [])
        ->assertStatus(422);
});

/*
 * mount() liest Routenname/-parameter aus request()->route() — aber Livewire::test()
 * kapselt einen eigenen, synthetischen Request und sieht die Route der aeusseren
 * Testmethode nicht (geprueft: nach einem echten $this->get('/us/in/meetups') liefert
 * request()->route() im Testcode noch den echten Match, ein direkt anschliessendes
 * Livewire::test('country.chooser') aber null/[] fuer currentRouteName/-params). mount()
 * selbst ist zwei duenne Aufrufe der Laravel-Routen-API (getName(), parameters()) ohne
 * eigene Logik; die Zusagen N1-N3 haengen an updatedCurrentCountry(), also wird dessen
 * Eingang (currentRouteName/currentRouteParams) direkt gesetzt — dieselben oeffentlichen
 * Properties, die mount() in Produktion aus der Route befuellt.
 */
it('redirects to the country-only route and drops the region when leaving a region page (N1)', function (string $regionRoute, string $plainRoute) {
    Livewire::test('country.chooser')
        ->set('currentRouteName', $regionRoute)
        ->set('currentRouteParams', ['country' => 'us', 'region' => 'in'])
        ->set('currentCountry', 'de')
        ->assertRedirectToRoute($plainRoute, ['country' => 'de']);
})->with([
    'meetups.index-region → meetups.index' => ['meetups.index-region', 'meetups.index'],
    'meetups.map-region → meetups.map' => ['meetups.map-region', 'meetups.map'],
    'cities.index-region → cities.index' => ['cities.index-region', 'cities.index'],
]);

it('leaves a plain country route pointing at the country route on a country change (N2)', function () {
    Livewire::test('country.chooser')
        ->set('currentRouteName', 'meetups.index')
        ->set('currentRouteParams', ['country' => 'us'])
        ->set('currentCountry', 'de')
        ->assertRedirectToRoute('meetups.index', ['country' => 'de']);
});

it('leaves a route without a region pair completely untouched on a country change (N3)', function () {
    // courses.index hat keine Regionsvariante — RegionRoutes::plain() muss null liefern,
    // und der Riegel darf dort nichts umbiegen.
    Livewire::test('country.chooser')
        ->set('currentRouteName', 'courses.index')
        ->set('currentRouteParams', ['country' => 'us'])
        ->set('currentCountry', 'de')
        ->assertRedirectToRoute('courses.index', ['country' => 'de']);
});
