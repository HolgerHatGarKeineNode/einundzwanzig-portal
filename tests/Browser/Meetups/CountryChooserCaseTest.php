<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;

/*
 * Issue #105: the country select's options are lowercased ISO codes while the value
 * bound to it came raw off the `{country}` route segment. Flux' `ui-selected` compares
 * with `===`, so an uppercase segment found no option and threw
 * `Uncaught Could not find option for value "DE"` while rendering.
 *
 * It only became reachable with #78: until then an uppercase segment 404'd, so the page
 * never rendered. Case-insensitive country codes made it resolve — and exposed the select.
 */
beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Frankfurt am Main',
    ]);
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Issue 105 Country Chooser Meetup',
        'slug' => 'issue-105-country-chooser',
        'visible_on_map' => true,
    ]);
});

it('renders the meetup list without JavaScript errors on an uppercase country segment', function (string $segment) {
    $browser = visit('/'.$segment.'/meetups');

    $browser->assertSee($this->meetup->name);

    // Without this the assertion below would also hold on a page that renders no select
    // at all — a chooser that stopped rendering must not read as "fixed".
    expect($browser->script('document.querySelectorAll("ui-select").length'))
        ->toBeGreaterThan(0);

    $browser->assertNoJavaScriptErrors();
})->with(['DE', 'de', 'De']);
