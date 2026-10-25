<?php

use App\Models\Country;

/*
 * The router used to accept any {country} segment: /en/meetups rendered, the
 * sidebar requested a flag that does not exist (404), and the calendar picker
 * handed "en" to Flux's country listbox, which threw "Could not find option for
 * value "en"" in the visitor's console (measured on production 2026-10-25). The
 * route binder in AppServiceProvider now answers such a segment with 404.
 */
beforeEach(function () {
    Country::factory()->create(['code' => 'DE', 'name' => 'Deutschland']);
});

it('serves a country segment that a country carries, in any case', function (string $path) {
    $this->get($path)->assertOk();
})->with(['lower case' => '/de/meetups', 'upper case' => '/DE/meetups']);

it('answers 404 for a country segment that no country carries', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'list page' => '/en/meetups',
    'map' => '/en/map',
    'optional-prefix group' => '/en/dashboard',
    'legacy redirect' => '/en/meetup/overview',
]);
