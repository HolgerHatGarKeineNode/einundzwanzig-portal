<?php

use App\Models\Country;
use App\Models\Region;
use Livewire\Livewire;

/*
 * Regressionsprobe fuer das Herausziehen der Routenzuordnung aus region/chooser.blade.php
 * in App\Support\RegionRoutes (P2). Die "supported=false ohne Regionspaar"-Haelfte von N4
 * deckt bereits tests/Feature/GeoDataVisibilityTest.php::'does not offer itself on a page
 * without a region route' ab — hier nur der Hin-/Rueckweg, der vorher nicht getestet war.
 */
beforeEach(function () {
    $this->us = Country::factory()->create(['code' => 'us']);
    Region::factory()->indiana()->create(['country_id' => $this->us->id]);
});

it('redirects to the region route when a region is chosen (N4)', function () {
    Livewire::test('region.chooser', ['country' => 'us', 'pageRoute' => 'meetups.index'])
        ->set('region', 'in')
        ->assertRedirectToRoute('meetups.index-region', ['country' => 'us', 'region' => 'in']);
});

it('redirects back to the country route when the region is cleared (N4)', function () {
    Livewire::test('region.chooser', ['country' => 'us', 'pageRoute' => 'meetups.index', 'region' => 'in'])
        ->set('region', '')
        ->assertRedirectToRoute('meetups.index', ['country' => 'us']);
});

/*
 * P3 (docs/plans/2026-08-24T1738-region-persistenz-navigation.md), N3: der Waehler auf der
 * Karte muss auf die KARTEN-Regionsroute fuehren (meetups.map-region), nicht auf die
 * erstbeste Route mit Regionsvariante (meetups.index-region). Ohne diesen Fall wuerde ein
 * Test, der nur meetups.index prueft, eine vertauschte pageRoute in map.blade.php nicht
 * bemerken — beide Zielrouten existieren, nur eine ist die richtige fuer die Karte.
 */
it('redirects to the MAP region route when a region is chosen on the map (N3)', function () {
    Livewire::test('region.chooser', ['country' => 'us', 'pageRoute' => 'meetups.map'])
        ->set('region', 'in')
        ->assertRedirectToRoute('meetups.map-region', ['country' => 'us', 'region' => 'in']);
});

it('redirects back to the plain map route when the region is cleared on the map (N3)', function () {
    Livewire::test('region.chooser', ['country' => 'us', 'pageRoute' => 'meetups.map', 'region' => 'in'])
        ->set('region', '')
        ->assertRedirectToRoute('meetups.map', ['country' => 'us']);
});
