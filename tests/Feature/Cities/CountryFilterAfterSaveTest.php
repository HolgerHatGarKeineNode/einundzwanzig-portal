<?php

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Livewire\Livewire;

/*
 * Issue #28: Nach dem Anlegen oder Bearbeiten einer Stadt sprang die Laenderauswahl
 * zurueck auf Deutschland.
 *
 * Ursache war route_with_country(): bei einem Livewire-Update heisst die Route
 * `livewire.update` und traegt kein 'country', und der Helfer fiel dann still auf 'de'.
 */
beforeEach(function () {
    $this->us = Country::factory()->create(['code' => 'us', 'name' => 'United States']);
    $this->de = Country::factory()->create(['code' => 'de', 'name' => 'Germany']);
    $this->user = User::factory()->create();
});

it('returns to the country that was being edited, not to Germany', function () {
    $city = City::factory()->create(['country_id' => $this->us->id, 'name' => 'Springfield']);

    Livewire::actingAs($this->user)
        ->withQueryParams([])
        ->test('cities.edit', ['city' => $city, 'country' => 'us'])
        ->set('name', 'Springfield Two')
        ->call('updateCity')
        ->assertRedirect(route('cities.index', ['country' => 'us']));
});

it('keeps an explicitly passed country instead of overwriting it from the session', function () {
    /*
     * Die beiden ersten Zweige des Helfers waren vertauscht: ein uebergebenes
     * 'country' wurde verworfen und aus der Sitzung neu gebaut.
     */
    session(['lang_country' => 'de-DE']);

    expect(route_with_country('cities.index', ['country' => 'us']))
        ->toBe(route('cities.index', ['country' => 'us']));
});

it('falls back to the session language rather than a hard-coded germany', function () {
    // Kein 'country' in der Route (Livewire-Update) und keins uebergeben.
    session(['lang_country' => 'en-US']);

    expect(route_with_country('cities.index'))
        ->toBe(route('cities.index', ['country' => 'us']));
});
