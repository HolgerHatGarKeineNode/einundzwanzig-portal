<?php

/*
|--------------------------------------------------------------------------
| staedte-identitaet P6 — php artisan cities:audit
|--------------------------------------------------------------------------
|
| Sechs Kategorien, read-only, Exitcode 1 bei Befunden. `--reverse` kostet pro
| Stadt eine echte 15-Sekunden-Bulk-Bremse (AuditCities::reverseCountryCode())
| — deshalb laeuft genau EIN Test mit `--reverse`, und er enthaelt exakt eine
| Stadt, um die Laufzeit auf ~15s zu begrenzen.
|
| P6-e (acht Neuenkirchen sind KEIN Befund) steht am Ende dieser Datei. Er war
| zuerst ein Fund und keine Zusage: `duplicateNames()` teilte sich die
| Gruppierung mit `mergeDuplicatesAfterTrim()` und meldete acht bereits
| saubere, byte-identische Namen als Befund. Beide Stellen sind korrigiert.
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// P6-a — namensdubletten: derselbe Offenburg-Fall wie in Produktion (untrimmter Name
// kollidiert nach TRIM mit einem sauberen im selben Land).
it('reports namensdubletten for a trim collision in the same country', function () {
    $country = Country::factory()->create();
    $dirty = City::factory()->create(['name' => 'Offenburg ', 'country_id' => $country->id]);
    $clean = City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id]);

    $exitCode = Artisan::call('cities:audit', ['--json' => true]);
    $findings = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($findings['namensdubletten'] ?? null)->not->toBeNull();

    $ids = collect($findings['namensdubletten'])->pluck('ids')->flatten()->all();
    expect($ids)->toContain($dirty->id)->toContain($clean->id);
});

// P6-a — namen_mit_leerzeichen: eine einzelne Stadt mit aeusserem Leerzeichen, ohne
// Kollision (fuer sich harmlos, als Muster nicht).
it('reports namen_mit_leerzeichen for a lone untrimmed name', function () {
    $city = City::factory()->create(['name' => 'Wehingen ']);

    $exitCode = Artisan::call('cities:audit', ['--json' => true]);
    $findings = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1);
    $ids = collect($findings['namen_mit_leerzeichen'])->pluck('id')->all();
    expect($ids)->toContain($city->id);
});

// P6-a — koordinate_ausserhalb: der reale Uznach-Fall (Schweizer LV03-Koordinaten
// direkt in den WGS84-Spalten).
it('reports koordinate_ausserhalb for out-of-range coordinates', function () {
    $city = City::factory()->create(['latitude' => 717943, 'longitude' => 231527]);

    $exitCode = Artisan::call('cities:audit', ['--json' => true]);
    $findings = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1);
    $ids = collect($findings['koordinate_ausserhalb'])->pluck('id')->all();
    expect($ids)->toContain($city->id);
});

// P6-a — nullinsel: 0/0, der Punkt im Atlantik.
it('reports nullinsel for 0/0 coordinates', function () {
    $city = City::factory()->create(['latitude' => 0, 'longitude' => 0]);

    $exitCode = Artisan::call('cities:audit', ['--json' => true]);
    $findings = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1);
    $ids = collect($findings['nullinsel'])->pluck('id')->all();
    expect($ids)->toContain($city->id);
});

// P6-a — gleiche_koordinaten: zwei VERSCHIEDENE Staedte auf demselben Punkt.
it('reports gleiche_koordinaten for two different cities sharing a point', function () {
    $a = City::factory()->create(['name' => 'Punkt A', 'latitude' => 12.3456, 'longitude' => 65.4321]);
    $b = City::factory()->create(['name' => 'Punkt B', 'latitude' => 12.3456, 'longitude' => 65.4321]);

    $exitCode = Artisan::call('cities:audit', ['--json' => true]);
    $findings = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1);
    $ids = collect($findings['gleiche_koordinaten'])->pluck('ids')->flatten()->all();
    expect($ids)->toContain($a->id)->toContain($b->id);
});

// P6-a — falsches_land: braucht --reverse und einen Netzaufruf. Gefaked, nicht gegen
// den echten Dienst — und mit genau EINER Stadt, weil jeder Treffer 15s Bulk-Bremse
// kostet (AuditCities::reverseCountryCode()).
it('reports falsches_land under --reverse when Nominatim disagrees with country_id', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => ['country_code' => 'us'],
        ], 200),
    ]);

    $country = Country::factory()->create(['code' => 'DE']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'latitude' => 52.52,
        'longitude' => 13.405,
    ]);

    $exitCode = Artisan::call('cities:audit', ['--reverse' => true, '--json' => true]);
    // Der Fortschrittsbalken der Reverse-Pruefung schreibt VOR dem JSON auf dieselbe
    // Ausgabe (createProgressBar()) — ohne den Zuschnitt auf das erste '{' waere das
    // kein gueltiges JSON mehr.
    $output = Artisan::output();
    $findings = json_decode(substr($output, strpos($output, '{')), true);

    expect($exitCode)->toBe(1);
    expect($findings['falsches_land'] ?? [])->toContain([
        'id' => $city->id,
        'name' => $city->name,
        'eingetragen' => 'de',
        'laut_osm' => 'us',
    ]);

    Http::assertSentCount(1);
})->group('slow');

// P6-b — DAS Kernversprechen: der Befehl schreibt nichts. Vollstaendiger
// Vorher/Nachher-Vergleich ALLER cities-Zeilen, mit mehreren gleichzeitigen Befunden.
it('writes absolutely nothing to the cities table', function () {
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Offenburg ', 'country_id' => $country->id]);
    City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id]);
    City::factory()->create(['latitude' => 0, 'longitude' => 0]);
    City::factory()->create(['latitude' => 717943, 'longitude' => 231527]);
    City::factory()->create(['name' => 'Shared A', 'latitude' => 1.0, 'longitude' => 2.0]);
    City::factory()->create(['name' => 'Shared B', 'latitude' => 1.0, 'longitude' => 2.0]);

    $before = DB::table('cities')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    $exitCode = Artisan::call('cities:audit');

    expect($exitCode)->toBe(1); // es gab tatsaechlich Befunde — sonst waere der Vergleich wertlos

    $after = DB::table('cities')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    expect($after)->toBe($before);
});

// P6-c — Exitcode 0 ohne Befunde.
it('exits 0 when there is nothing to report', function () {
    City::factory()->create(['name' => 'Sauber', 'latitude' => 10.0, 'longitude' => 10.0]);

    $exitCode = Artisan::call('cities:audit');

    expect($exitCode)->toBe(0);
});

// P6-c — Exitcode 1 mit Befunden (Gegenprobe zum Test darueber).
it('exits 1 when there is something to report', function () {
    City::factory()->create(['latitude' => 0, 'longitude' => 0]);

    $exitCode = Artisan::call('cities:audit');

    expect($exitCode)->toBe(1);
});

// P6-d — --json liefert gueltiges JSON.
it('emits valid, well-formed JSON with --json', function () {
    City::factory()->create(['latitude' => 0, 'longitude' => 0]);
    City::factory()->create(['name' => 'Sauber ']);

    Artisan::call('cities:audit', ['--json' => true]);
    $output = Artisan::output();

    $decoded = json_decode($output, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded)->toBeArray()
        ->and($decoded)->toHaveKey('nullinsel')
        ->and($decoded)->toHaveKey('namen_mit_leerzeichen');
});

/*
 * P6-e — der Fund, der zur Zusage wurde.
 *
 * Hier stand bis zum 2026-08-25 ein Kommentarblock: `duplicateNames()` meldete acht
 * reale Neuenkirchen als Befund, weil es dieselbe Gruppierung benutzte wie die
 * P2-Migration — dieselbe Zeile Logik, an zwei Stellen dupliziert, zwei Symptome. Der
 * Koordinator hat beide korrigiert (`COUNT(DISTINCT name) > 1`); der Fund ist jetzt der
 * Test darunter.
 *
 * Warum das die wichtigste Zusage dieses Kommandos ist: ein Pruefkommando, das im
 * Normalbetrieb immer anschlaegt, wird nach der dritten Woche ignoriert. Nach dem
 * Import aus Issue #33 waere es dauerhaft rot gewesen — und damit genau dann wertlos,
 * wenn man es zum ersten Mal wirklich braucht.
 */
it('does not report eight genuine same-name places — only whitespace collisions count', function () {
    $country = Country::factory()->create();
    neuenkirchenCities($country);

    $this->artisan('cities:audit')->assertSuccessful();

    // Gegenprobe: EINE echte Leerzeichen-Dublette macht denselben Lauf rot. Ohne sie
    // waere ein Kommando, das gar nichts meldet, ebenfalls gruen.
    City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id, 'slug' => null]);
    City::factory()->create(['name' => 'Offenburg ', 'country_id' => $country->id, 'slug' => null]);

    $this->artisan('cities:audit')->assertFailed();
});
