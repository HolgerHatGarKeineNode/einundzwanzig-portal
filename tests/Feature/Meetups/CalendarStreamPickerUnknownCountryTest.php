<?php

use App\Models\Country;

/*
 * The router accepts any {country} segment, so /en/meetups renders. The picker
 * used to hand that segment to Flux's country listbox as its x-model, and Flux
 * throws "Could not find option for value "en"" in the visitor's console for a
 * value that is not among the options (measured on production 2026-10-25).
 */
beforeEach(function () {
    Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    Country::factory()->create(['code' => 'at', 'name' => 'Österreich']);
});

it('keeps the route country as the picker default when it is a listed country', function () {
    $this->get('/at/meetups')->assertOk()->assertSee("country: 'at'", false);
});

it('falls back to a listed country for an unknown country segment', function () {
    $this->get('/en/meetups')
        ->assertOk()
        ->assertDontSee("country: 'en'", false)
        ->assertSee("country: '".mb_strtolower(config('app.domain_country', 'de'))."'", false);
});
