<?php

use Livewire\Livewire;

/*
 * Bonus-Zusage: welcome navigiert unter Bitcoin Diana wirklich nach /us/in/meetups
 * bzw. /us/in/map, nicht nur der Helper isoliert (siehe country_or_region_route()-
 * Tests in CountryOrRegionRouteHelperTest.php).
 */
beforeEach(function () {
    config(['app.domain_country' => 'us', 'app.domain_region' => 'in']);
    session(['lang_country' => 'en-US']);
});

it('redirects goToMeetups to the Indiana region route', function () {
    Livewire::test('welcome')
        ->call('goToMeetups')
        ->assertRedirect(route('meetups.index-region', ['country' => 'us', 'region' => 'in']));
});

it('redirects goToMap to the Indiana region route', function () {
    Livewire::test('welcome')
        ->call('goToMap')
        ->assertRedirect(route('meetups.map-region', ['country' => 'us', 'region' => 'in']));
});
