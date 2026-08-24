<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Livewire\Livewire;

/*
 * P4/P5 lassen die vier Aufrufe in cities/create und cities/edit ausdruecklich
 * unangetastet (docs/plans/2026-08-24T1738-region-persistenz-navigation.md, "Out" und
 * Risiko 1): Bekaeme der Redirect nach dem Speichern eine Region, wuerde auf Bitcoin
 * Diana jedes Speichern einer US-Stadt nach /us/in/cities fuehren, und
 * cities/index.blade.php filtert hart auf cities.region_id — der Nutzer saehe seine
 * gerade gespeicherte Stadt nicht mehr (Fehlerform von Issue #28 auf der Regionsachse).
 *
 * Verhaltenstest statt Grep auf den Helfernamen: config('app.domain_region') wird
 * ausdruecklich wie auf portal.bitcoindiana.org gesetzt, dann wird tatsaechlich
 * gespeichert und der Redirect-Pfad geprueft — das deckt sowohl "welcher Helfer wird
 * gerufen" als auch "was kommt tatsaechlich dabei heraus" in einem Schritt ab. Die
 * statische Gegenprobe (grep auf die vier Zeilen) steht in
 * CityFormHelperCallsStayPlainTest.php daneben, weil sie den Fall abdeckt, dass jemand
 * einen FUENFTEN, region-bewussten Aufruf ergaenzt, ohne einen bestehenden zu aendern.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    test()->actingAs($this->user);

    config(['app.domain_country' => 'us', 'app.domain_region' => 'in']);

    $this->usa = Country::factory()->create(['code' => 'us']);
    $this->indiana = Region::factory()->indiana()->create(['country_id' => $this->usa->id]);
});

it('redirects to the plain country route after creating a city, even under a region-biased domain (N5, create)', function () {
    Livewire::test('cities.create')
        ->set('name', 'Fort Wayne Redirect Test')
        ->set('country_id', $this->usa->id)
        ->set('region_id', $this->indiana->id)
        ->set('latitude', 41.0793)
        ->set('longitude', -85.1394)
        ->call('createCity')
        ->assertHasNoErrors()
        ->assertRedirect(route('cities.index', ['country' => 'us']));
});

it('redirects to the plain country route after updating a city, even under a region-biased domain (N5, edit)', function () {
    $city = City::factory()->create([
        'country_id' => $this->usa->id,
        'region_id' => $this->indiana->id,
        'created_by' => $this->user->id,
    ]);

    Livewire::test('cities.edit', ['city' => $city])
        ->set('name', 'Renamed via Edit Redirect Test')
        ->call('updateCity')
        ->assertHasNoErrors()
        ->assertRedirect(route('cities.index', ['country' => 'us']));
});
