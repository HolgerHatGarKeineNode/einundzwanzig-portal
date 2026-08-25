<?php

/*
|--------------------------------------------------------------------------
| Issue #33 / staedte-identitaet P1 — City::resolveOrCreate()
|--------------------------------------------------------------------------
|
| Model-Ebene, nicht HTTP: resolveOrCreate() ist die gemeinsame Logik hinter
| REST (CityController::store/update) und MCP (CreateCityTool/UpdateCityTool)
| — beide rufen sie unveraendert auf. Die niedrigste Ebene, die den Fehler
| faengt, ist hier der Modelltest; die Wired-Tests in
| tests/Feature/Api/CityIdentityResolutionApiTest.php und
| tests/Feature/Mcp/CityIdentityResolutionMcpTest.php pruefen nur noch, dass
| jeder Schreibpfad diese Antworten unveraendert durchreicht (Statuscodes,
| Fehlerformat).
|
| N9 — Regionsfreiheit: jeder Kernfall (N2, N3, N4) laeuft zweimal ueber ein
| Dataset, einmal mit einem Land, das Regionen hat (DE), einmal ohne (AT).
| region_id wird dabei nie gesetzt oder abgefragt — genau das ist die Zusage.
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Illuminate\Validation\ValidationException;

dataset('mit und ohne Regionen', [
    'DE (hat Regionen)' => fn () => ['code' => 'de', 'withRegion' => true],
    'AT (hat keine Regionen)' => fn () => ['code' => 'at', 'withRegion' => false],
]);

// N1 — Gleicher Name, VERSCHIEDENE Laender: legitim und anlegbar, aber (wie jede
// Neuanlage neben einem gleichnamigen Ort — Schritt 4/5 im Model-Docblock) NICHT
// stillschweigend: der zweite Namensvetter in einem anderen Land braucht denselben
// Identifier wie ein zweites Georgetown im selben Land. Ohne ihn siehe N3 — das ist
// genau der Fall, der Issue #33 ausgeloest hat (Springfield/MO bekam sonst still
// Springfield/IL). Mit confirm_duplicate koennen beide nebeneinander stehen.
it('allows the same name in different countries once confirmed (Paris FR / Paris US)', function () {
    $fr = Country::factory()->create(['code' => 'fr']);
    $us = Country::factory()->create(['code' => 'us']);

    $creator = User::factory()->create();

    $paris1 = City::resolveOrCreate([
        'name' => 'Paris', 'country_id' => $fr->id,
        'latitude' => 48.8566, 'longitude' => 2.3522,
        'created_by' => $creator->id,
    ]);
    $paris2 = City::resolveOrCreate([
        'name' => 'Paris', 'country_id' => $us->id,
        'latitude' => 33.6609, 'longitude' => -95.5555,
        'created_by' => $creator->id,
        'confirm_duplicate' => true,
    ]);

    expect($paris1->wasRecentlyCreated)->toBeTrue()
        ->and($paris2->wasRecentlyCreated)->toBeTrue()
        ->and($paris1->id)->not->toBe($paris2->id)
        ->and($paris1->country_id)->toBe($fr->id)
        ->and($paris2->country_id)->toBe($us->id);

    expect(City::query()->where('name', 'Paris')->count())->toBe(2);
});

// N2 — Gleicher Name + gleiches Land, kein Identifier: Bestand, keine Neuanlage.
it('resolves to the existing city for the same name and country, region-free', function (array $data) {
    $country = Country::factory()->create(['code' => $data['code']]);
    $region = $data['withRegion'] ? Region::factory()->create(['country_id' => $country->id]) : null;

    $existing = City::factory()->create([
        'name' => 'Springfield',
        'country_id' => $country->id,
        'region_id' => $region?->id,
    ]);

    $resolved = City::resolveOrCreate([
        'name' => 'Springfield',
        'country_id' => $country->id,
        'latitude' => 1.0, 'longitude' => 1.0,
    ]);

    expect($resolved->wasRecentlyCreated)->toBeFalse()
        ->and($resolved->id)->toBe($existing->id);

    expect(City::query()->where('name', 'Springfield')->count())->toBe(1);
})->with('mit und ohne Regionen');

// N3 — Name existiert in einem ANDEREN Land, kein Identifier: 422/Exception, region-frei.
it('refuses to create when the name exists only in a different country, region-free', function (array $data) {
    $country = Country::factory()->create(['code' => $data['code']]);
    $otherCountry = Country::factory()->create();
    $region = $data['withRegion'] ? Region::factory()->create(['country_id' => $country->id]) : null;

    City::factory()->create(['name' => 'Springfield', 'country_id' => $otherCountry->id]);

    expect(fn () => City::resolveOrCreate([
        'name' => 'Springfield',
        'country_id' => $country->id,
        'latitude' => 1.0, 'longitude' => 1.0,
    ]))->toThrow(ValidationException::class);

    expect(City::query()->where('name', 'Springfield')->count())->toBe(1);
    // Region spielte an keiner Stelle eine Rolle — nichts wurde angelegt, das eine
    // region_id haette tragen koennen.
    unset($region);
})->with('mit und ohne Regionen');

// N4 — MEHRERE Treffer im Land: 422 mit unterscheidbarer Liste (id + Koordinaten),
// region-frei. Acht Neuenkirchen, nicht "achtmal dasselbe Wort".
it('lists all eight candidates distinguishably by id and coordinates when the name is ambiguous, region-free', function (array $data) {
    $country = Country::factory()->create(['code' => $data['code']]);
    $region = $data['withRegion'] ? Region::factory()->create(['country_id' => $country->id]) : null;
    $cities = neuenkirchenCities($country, $region);

    try {
        City::resolveOrCreate([
            'name' => 'Neuenkirchen',
            'country_id' => $country->id,
            'latitude' => 52.5, 'longitude' => 8.0,
        ]);
        $this->fail('Erwartete ValidationException bei acht gleichnamigen Staedten.');
    } catch (ValidationException $e) {
        $message = $e->validator->errors()->first('name');

        // Jede der acht ids muss vorkommen, und jede Koordinate muss vorkommen — sonst
        // steht "Neuenkirchen" achtmal im Text und niemand kann waehlen.
        foreach ($cities as $city) {
            expect($message)->toContain('#'.$city->id);
            expect($message)->toContain(number_format((float) $city->latitude, 4));
        }
    }

    expect(City::query()->where('name', 'Neuenkirchen')->count())->toBe(8);
})->with('mit und ohne Regionen');

// N5 — confirm_duplicate legt ein zweites Georgetown im selben Land an, AUCH wenn
// bereits eines existiert (Reihenfolgetest: ohne "Bestaetigung vor Namenssuche"
// faende die Suche immer das erste).
it('creates a second Georgetown in the same country with confirm_duplicate, even when one already exists', function () {
    $country = Country::factory()->create();
    $first = City::factory()->create(['name' => 'Georgetown', 'country_id' => $country->id]);

    $second = City::resolveOrCreate([
        'name' => 'Georgetown',
        'country_id' => $country->id,
        'latitude' => 38.32, 'longitude' => -85.87,
        'confirm_duplicate' => true,
        'created_by' => User::factory()->create()->id,
    ]);

    expect($second->wasRecentlyCreated)->toBeTrue()
        ->and($second->id)->not->toBe($first->id);

    expect(City::query()->where('name', 'Georgetown')->where('country_id', $country->id)->count())->toBe(2);
});

// N6 — Eine mitgeschickte OSM-Referenz, die noch zu keiner Stadt gehoert, legt an —
// AUCH wenn der Name im Land mehrdeutig ist (acht Neuenkirchen).
it('creates via a fresh OSM reference even when the name is ambiguous in that country', function () {
    $country = Country::factory()->create();
    neuenkirchenCities($country);

    $created = City::resolveOrCreate([
        'name' => 'Neuenkirchen',
        'country_id' => $country->id,
        'latitude' => 52.9, 'longitude' => 8.9,
        'osm_type' => 'relation',
        'osm_id' => 900123456,
        'created_by' => User::factory()->create()->id,
    ]);

    expect($created->wasRecentlyCreated)->toBeTrue()
        ->and($created->osm_type)->toBe('relation')
        ->and($created->osm_id)->toBe(900123456);

    expect(City::query()->where('name', 'Neuenkirchen')->count())->toBe(9);
});

// N7 — Dieselbe OSM-Referenz mit einem ANDEREN Namen trifft denselben Datensatz.
it('matches the same record via OSM reference even when the submitted name differs', function () {
    $country = Country::factory()->create();
    $city = City::factory()->create([
        'name' => 'Neuenkirchen',
        'country_id' => $country->id,
        'osm_type' => 'relation',
        'osm_id' => 900123456,
    ]);

    $resolved = City::resolveOrCreate([
        'name' => 'Neuenkirchen (Oldenburg)', // abweichender Name im Request
        'country_id' => $country->id,
        'latitude' => 1.0, 'longitude' => 1.0,
        'osm_type' => 'relation',
        'osm_id' => 900123456,
    ]);

    expect($resolved->wasRecentlyCreated)->toBeFalse()
        ->and($resolved->id)->toBe($city->id)
        // Der Name wurde NICHT durch das Auffinden ueberschrieben — matchingOsmReference
        // gibt den Bestand unveraendert zurueck, resolveOrCreate ist keine Update-Methode.
        ->and($resolved->name)->toBe('Neuenkirchen');
});

// N8 — Getrimmter Name matcht den ungetrimmten Bestand (und umgekehrt).
it('matches a trimmed name against an untrimmed existing name (Offenburg)', function () {
    $country = Country::factory()->create();
    $existing = City::factory()->create(['name' => 'Offenburg ', 'country_id' => $country->id]);

    $resolved = City::resolveOrCreate([
        'name' => 'Offenburg',
        'country_id' => $country->id,
        'latitude' => 1.0, 'longitude' => 1.0,
    ]);

    expect($resolved->wasRecentlyCreated)->toBeFalse()
        ->and($resolved->id)->toBe($existing->id);
});
