<?php

use App\Models\Country;
use App\Models\Region;

/*
 * P3 (docs/plans/2026-08-24T1738-region-persistenz-navigation.md): die Kartenseite bindet
 * jetzt <livewire:region.chooser/> ein, wie schon meetups.index und cities.index seit P2
 * (resources/views/livewire/meetups/map.blade.php). Diese Datei deckt, was an der Karte
 * selbst neu ist: der Waehler erscheint auf allen sechs US-Regionsseiten (N1), bleibt auf
 * einem Land ohne Regionen aus (N2), und die Karte rendert nach der Umstellung der
 * Kopfleiste auf flex weiterhin vollstaendig (N4). Die Zielroute des Waehlers selbst (N3)
 * steht in RegionChooserRoundTripTest.php, direkt neben dem analogen Fall fuer
 * meetups.index.
 *
 * Anker fuer N1/N2: das Attribut `wire:model.live="region"` auf dem gerenderten
 * <flux:select>, nicht assertSeeLivewire('region.chooser'). Die Komponente ist auf jeder
 * Seite eingebettet und schreibt ihr wire:snapshot auch dann ins HTML, wenn
 * $supported/$regions in chooser.blade.php das sichtbare <flux:select> unterdruecken —
 * assertSeeLivewire wuerde also unabhaengig davon gruen bleiben, ob der Waehler
 * tatsaechlich zu sehen ist. Empirisch mit pest --agent geprueft: auf /de/map (keine
 * Regionen) fehlt wire:model.live="region", waehrend "region.chooser" im HTML bleibt.
 */
beforeEach(function () {
    $this->us = Country::factory()->create(['code' => 'us']);
    Region::factory()->indiana()->create(['country_id' => $this->us->id]);
});

it('shows the region chooser on all six US region pages (N1)', function (string $path) {
    $this->get($path)->assertOk()->assertSee('wire:model.live="region"', false);
})->with([
    'map, country' => ['/us/map'],
    'map, region' => ['/us/in/map'],
    'meetups, country' => ['/us/meetups'],
    'meetups, region' => ['/us/in/meetups'],
    'cities, country' => ['/us/cities'],
    'cities, region' => ['/us/in/cities'],
]);

it('does not show the region chooser on the map for a country without regions (N2)', function () {
    Country::factory()->create(['code' => 'de']);

    $this->get('/de/map')->assertOk()->assertDontSee('wire:model.live="region"', false);
});

it('still renders the map fully after the header became a flex row (N4)', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('id="map"', false)
        ->assertSee('x-ref="map"', false);
})->with([
    'country map' => ['/us/map'],
    'region map' => ['/us/in/map'],
]);
