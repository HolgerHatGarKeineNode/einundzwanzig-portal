<?php

/*
|--------------------------------------------------------------------------
| Issue #33 — Portal-Verdrahtung (cities/create, cities/edit,
| meetups/create::createCity, meetups/edit::createCity)
|--------------------------------------------------------------------------
|
| WICHTIGER UNTERSCHIED zu REST/MCP, gemessen beim Bauen dieser Datei: keiner
| der vier Portal-Schreibpfade ruft City::resolveOrCreate() auf. Alle vier
| legen weiterhin direkt per City::create() an, geschuetzt durch eine eigene,
| landesbezogene Rule::unique('cities','name')->where('country_id', ...) in
| ihrer eigenen validate()-Regel (cities/create.blade.php:71-74,
| cities/edit.blade.php:132-137, meetups/create.blade.php:51-54,
| meetups/edit.blade.php:97-100). Zwei Folgen, beide belegt:
|
|  1. Kein confirm_duplicate, keine OSM-Ausnahme im Portal. N4/N5/N6 lassen
|     sich hier NICHT pruefen, weil es den Mechanismus, den sie pruefen
|     wuerden, im Portal nicht gibt — eine im Bauen fehlende Verdrahtung, kein
|     Testluecke. Bei "gleicher Name + gleiches Land" blockiert das Portal die
|     Neuanlage komplett (Validierungsfehler); es gibt NIE den Bestand mit 200
|     zurueck wie REST/MCP. Das ist eine dritte, eigene Verhaltensweise neben
|     REST/MCP — dokumentiert hier, nicht bewertet.
|  2. N8 (Trim-Aequivalenz) ist im Portal GEBROCHEN: die unique-Regel prueft
|     den UNGETRIMMTEN Eingabewert, getrimmt wird erst danach vor dem
|     create() — ein Name mit Leerzeichen-Suffix umgeht die
|     Landes-Kollisionspruefung und legt eine echte Dublette an. Belegt per
|     vendor/bin/pest --agent (2026-08-25), siehe Abschlussbericht. Kein Test
|     dafuer HIER (waere dauerhaft rot); die funktionierenden Portal-Pfade
|     (exakter String-Match) sind unten mitgeprueft.
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\Region;
use Livewire\Livewire;

dataset('mit und ohne Regionen', [
    'DE (hat Regionen)' => fn () => ['code' => 'de', 'withRegion' => true],
    'AT (hat keine Regionen)' => fn () => ['code' => 'at', 'withRegion' => false],
]);

// N1 — cities/create: gleicher Name, anderes Land, keine Kollision.
it('allows the same name in a different country via cities.create', function () {
    actingAsUser();
    $fr = Country::factory()->create();
    City::factory()->create(['name' => 'Paris', 'country_id' => $fr->id]);
    $us = Country::factory()->create();

    Livewire::test('cities.create')
        ->set('name', 'Paris')
        ->set('country_id', $us->id)
        ->set('latitude', 33.66)
        ->set('longitude', -95.55)
        ->call('createCity')
        ->assertHasNoErrors();

    expect(City::query()->where('name', 'Paris')->count())->toBe(2);
});

// N2-Analog — cities/create: gleicher Name + gleiches Land wird BLOCKIERT (nicht: der
// Bestand wird zurueckgegeben — anders als REST/MCP), region-frei.
it('blocks a second city of the same name in the same country via cities.create, region-free', function (array $data) {
    actingAsUser();
    $country = Country::factory()->create(['code' => $data['code']]);
    $region = $data['withRegion'] ? Region::factory()->create(['country_id' => $country->id]) : null;
    City::factory()->create(['name' => 'Georgetown', 'country_id' => $country->id, 'region_id' => $region?->id]);

    Livewire::test('cities.create')
        ->set('name', 'Georgetown')
        ->set('country_id', $country->id)
        ->set('latitude', 38.32)
        ->set('longitude', -85.87)
        ->call('createCity')
        ->assertHasErrors(['name' => 'unique']);

    expect(City::query()->where('name', 'Georgetown')->count())->toBe(1);
})->with('mit und ohne Regionen');

// N11 — cities/edit: Rename auf einen im SELBEN Land belegten Namen wird geblockt.
it('blocks renaming to a name already used in the same country via cities.edit', function () {
    $user = actingAsUser();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Regensburg', 'country_id' => $country->id]);
    $mine = City::factory()->create(['created_by' => $user->id, 'name' => 'Ansbach', 'country_id' => $country->id]);

    Livewire::test('cities.edit', ['city' => $mine])
        ->set('name', 'Regensburg')
        ->set('country_id', $country->id)
        ->set('latitude', 49.0)
        ->set('longitude', 12.0)
        ->call('updateCity')
        ->assertHasErrors(['name' => 'unique']);

    expect($mine->fresh()->name)->toBe('Ansbach');
});

// N11 — cities/edit: Rename auf einen in einem ANDEREN Land belegten Namen ist erlaubt.
it('allows renaming to a name already used in a different country via cities.edit', function () {
    $user = actingAsUser();
    City::factory()->create(['name' => 'Regensburg']);
    $ownCountry = Country::factory()->create();
    $mine = City::factory()->create(['created_by' => $user->id, 'name' => 'Ansbach', 'country_id' => $ownCountry->id]);

    Livewire::test('cities.edit', ['city' => $mine])
        ->set('name', 'Regensburg')
        ->set('country_id', $ownCountry->id)
        ->set('latitude', 49.0)
        ->set('longitude', 12.0)
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($mine->fresh()->name)->toBe('Regensburg');
});

/*
|--------------------------------------------------------------------------
| meetups/create und meetups/edit: dieselbe landesbezogene Bremse in der
| "Stadt hinzufuegen"-Modal (createCity()), unabhaengig vom Meetup selbst.
|--------------------------------------------------------------------------
*/

it('blocks a same-name/same-country city via the meetups.create add-city modal', function () {
    actingAsUser();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Salem', 'country_id' => $country->id]);

    Livewire::test('meetups.create')
        ->set('newCityName', 'Salem')
        ->set('newCityCountryId', $country->id)
        ->set('newCityLatitude', 38.6)
        ->set('newCityLongitude', -86.1)
        ->call('createCity')
        ->assertHasErrors(['newCityName' => 'unique']);

    expect(City::query()->where('name', 'Salem')->count())->toBe(1);
});

it('allows a same-name city in a different country via the meetups.create add-city modal', function () {
    actingAsUser();
    City::factory()->create(['name' => 'Salem']);
    $otherCountry = Country::factory()->create();

    Livewire::test('meetups.create')
        ->set('newCityName', 'Salem')
        ->set('newCityCountryId', $otherCountry->id)
        ->set('newCityLatitude', 38.6)
        ->set('newCityLongitude', -86.1)
        ->call('createCity')
        ->assertHasNoErrors();

    expect(City::query()->where('name', 'Salem')->count())->toBe(2);
});

it('blocks a same-name/same-country city via the meetups.edit add-city modal', function () {
    $user = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Salem', 'country_id' => $country->id]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('newCityName', 'Salem')
        ->set('newCityCountryId', $country->id)
        ->set('newCityLatitude', 38.6)
        ->set('newCityLongitude', -86.1)
        ->call('createCity')
        ->assertHasErrors(['newCityName' => 'unique']);

    expect(City::query()->where('name', 'Salem')->count())->toBe(1);
});
