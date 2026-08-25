<?php

/*
|--------------------------------------------------------------------------
| Issue #33 — Portal-Verdrahtung (cities/create, cities/edit,
| meetups/create::createCity, meetups/edit::createCity)
|--------------------------------------------------------------------------
|
| STAND 2026-08-25 (staedte-identitaet P4), NICHT mehr der Stand, den diese
| Datei urspruenglich dokumentierte: der Kommentarblock hier behauptete bis
| zu diesem Commit, keiner der vier Portal-Pfade kenne confirm_duplicate oder
| eine Kandidatenliste — das ist inzwischen VERALTET. P4 hat allen vier
| Formularen den Bestaetigungsweg nachgeruestet:
|
|  - cities/create.blade.php: `$confirmDuplicate`, `$duplicateCandidates`
|    (id, Region falls vorhanden, Koordinaten).
|  - cities/edit.blade.php: `$confirmDuplicate` — OHNE Kandidatenliste, die
|    Checkbox erscheint erst nach `@error('name')`.
|  - meetups/create.blade.php + meetups/edit.blade.php (Modal "Stadt
|    hinzufuegen"): `$confirmDuplicateCity`, `$duplicateCityCandidates`
|    (id, Koordinaten — kein Region-Feld in diesem kleineren Modal).
|
| Weiterhin richtig, weil unabhaengig von P4: keiner der vier Pfade ruft
| City::resolveOrCreate() auf (bewusst — ein Formular mit der Aufschrift
| "Stadt anlegen" darf nicht kommentarlos den Bestand zurueckgeben). Bei
| "gleicher Name + gleiches Land" OHNE Bestaetigung blockiert das Portal die
| Neuanlage komplett; es gibt NIE den Bestand mit 200 zurueck wie REST/MCP.
|
| P4-d — der Trim-Fund, den diese Datei urspruenglich als "kein Test dafuer,
| waere dauerhaft rot" dokumentierte, ist jetzt REGRESSIONSGETESTET: alle vier
| Formulare trimmen inzwischen VOR der Validierung (siehe die
| Docblock-Kommentare in den Blade-Dateien selbst). Die Tests am Ende dieser
| Datei sichern genau das ab.
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

/*
|--------------------------------------------------------------------------
| P4 — der Bestaetigungsweg selbst: Kandidatenliste, Unterscheidbarkeit,
| Anlegen mit Bestaetigung, Trim-Regression (der Fund von N8), Rename ueber
| cities.edit mit confirmDuplicate.
|--------------------------------------------------------------------------
*/

// P4-a + P4-b — cities.create: ohne Bestaetigung blockiert, UND die
// Kandidatenliste ist gefuellt und unterscheidbar (id, Region falls
// vorhanden, Koordinaten) — nicht achtmal derselbe Name.
it('blocks without confirmation and lists distinguishable candidates via cities.create', function () {
    actingAsUser();
    $country = Country::factory()->create(['code' => 'us']);
    $region = Region::factory()->create(['country_id' => $country->id, 'code' => 'in']);

    $withRegion = City::factory()->create([
        'name' => 'Georgetown', 'country_id' => $country->id, 'region_id' => $region->id,
        'latitude' => 38.3223, 'longitude' => -85.8730,
    ]);
    $withoutRegion = City::factory()->create([
        'name' => 'Georgetown', 'country_id' => $country->id, 'region_id' => null,
        'latitude' => 40.0475, 'longitude' => -84.8330,
    ]);

    $component = Livewire::test('cities.create')
        ->set('name', 'Georgetown')
        ->set('country_id', $country->id)
        ->set('latitude', 41.02)
        ->set('longitude', -85.05)
        ->call('createCity')
        ->assertHasErrors(['name' => 'unique']);

    // Nichts angelegt — der Riegel hat gehalten.
    expect(City::query()->where('name', 'Georgetown')->count())->toBe(2);

    $candidates = collect($component->get('duplicateCandidates'));
    expect($candidates)->toHaveCount(2);

    $byId = $candidates->keyBy('id');
    expect($byId->has($withRegion->id))->toBeTrue()
        ->and($byId->has($withoutRegion->id))->toBeTrue()
        // Unterscheidbar: die Koordinaten der beiden Kandidaten sind verschieden,
        // nicht "Georgetown" zweimal ohne jeden Unterschied.
        ->and($byId[$withRegion->id]['latitude'])->toBe(38.3223)
        ->and($byId[$withoutRegion->id]['latitude'])->toBe(40.0475)
        // Region nur, wo es eine gibt — nicht als leeres Rauschen erzwungen.
        ->and($byId[$withRegion->id]['region'])->toBe('IN')
        ->and($byId[$withoutRegion->id]['region'])->toBeNull();
});

// P4-c — mit Bestaetigung: angelegt, trotz bestehendem Georgetown im selben Land.
it('creates via cities.create with confirmDuplicate despite an existing same-name city', function () {
    actingAsUser();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Georgetown', 'country_id' => $country->id]);

    Livewire::test('cities.create')
        ->set('name', 'Georgetown')
        ->set('country_id', $country->id)
        ->set('latitude', 38.32)
        ->set('longitude', -85.87)
        ->set('confirmDuplicate', true)
        ->call('createCity')
        ->assertHasNoErrors();

    expect(City::query()->where('name', 'Georgetown')->where('country_id', $country->id)->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| P4-d — Regressionstest zum N8-Fund: 'Offenburg ' bei vorhandenem
| 'Offenburg' wird in ALLEN VIER Formularen blockiert, weil sie inzwischen
| VOR der Validierung trimmen. Mutationsprobe (durchgefuehrt, nicht Teil
| dieser Datei): die vier `trim()`-Aufrufe in createCity()/updateCity() hinter
| die validate()-Aufrufe verschoben — alle vier hier stehenden Tests wurden
| ROT und legten tatsaechlich eine zweite 'Offenburg'-Zeile an, danach
| zurueckgesetzt. Das haelt den N8-Fund fest.
|--------------------------------------------------------------------------
*/

it('blocks "Offenburg " against an existing "Offenburg" via cities.create (N8 regression)', function () {
    actingAsUser();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id]);

    Livewire::test('cities.create')
        ->set('name', 'Offenburg ')
        ->set('country_id', $country->id)
        ->set('latitude', 48.4744)
        ->set('longitude', 7.9438)
        ->call('createCity')
        ->assertHasErrors(['name' => 'unique']);

    expect(City::query()->where('country_id', $country->id)->count())->toBe(1);
});

it('blocks renaming to "Offenburg " against an existing "Offenburg" via cities.edit (N8 regression)', function () {
    $user = actingAsUser();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id]);
    $mine = City::factory()->create(['created_by' => $user->id, 'name' => 'Lahr', 'country_id' => $country->id]);

    Livewire::test('cities.edit', ['city' => $mine])
        ->set('name', 'Offenburg ')
        ->set('country_id', $country->id)
        ->set('latitude', 48.4744)
        ->set('longitude', 7.9438)
        ->call('updateCity')
        ->assertHasErrors(['name' => 'unique']);

    expect($mine->fresh()->name)->toBe('Lahr');
});

it('blocks "Offenburg " via the meetups.create add-city modal (N8 regression)', function () {
    actingAsUser();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id]);

    Livewire::test('meetups.create')
        ->set('newCityName', 'Offenburg ')
        ->set('newCityCountryId', $country->id)
        ->set('newCityLatitude', 48.4744)
        ->set('newCityLongitude', 7.9438)
        ->call('createCity')
        ->assertHasErrors(['newCityName' => 'unique']);

    expect(City::query()->where('country_id', $country->id)->count())->toBe(1);
});

it('blocks "Offenburg " via the meetups.edit add-city modal (N8 regression)', function () {
    $user = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('newCityName', 'Offenburg ')
        ->set('newCityCountryId', $country->id)
        ->set('newCityLatitude', 48.4744)
        ->set('newCityLongitude', 7.9438)
        ->call('createCity')
        ->assertHasErrors(['newCityName' => 'unique']);

    expect(City::query()->where('country_id', $country->id)->count())->toBe(1);
});

// P4-f — cities.edit: Rename auf einen im selben Land belegten Namen wird MIT
// confirmDuplicate erlaubt (Gegenprobe zu N11 "blocks renaming..." oben).
it('allows renaming to a name already used in the same country via cities.edit with confirmDuplicate', function () {
    $user = actingAsUser();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Regensburg', 'country_id' => $country->id]);
    $mine = City::factory()->create(['created_by' => $user->id, 'name' => 'Ansbach', 'country_id' => $country->id]);

    Livewire::test('cities.edit', ['city' => $mine])
        ->set('name', 'Regensburg')
        ->set('country_id', $country->id)
        ->set('latitude', 49.0)
        ->set('longitude', 12.0)
        ->set('confirmDuplicate', true)
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($mine->fresh()->name)->toBe('Regensburg');
});
