<?php

use App\Models\Country;
use App\Models\Region;
use App\Support\SupportedCountries;
use Illuminate\Support\Str;

it('imports the US states and is idempotent', function () {
    $country = Country::factory()->create(['code' => 'us']);

    $this->artisan('regions:import', ['--country' => ['us']])->assertSuccessful();

    expect(Region::where('country_id', $country->id)->count())->toBe(51);

    $indiana = Region::findByCountryCodeAndCode('us', 'in');
    expect($indiana)->not->toBeNull()
        ->and($indiana->name)->toBe('Indiana')
        ->and($indiana->slug)->toBe('indiana')
        ->and($indiana->isoCode())->toBe('US-IN');

    // Zweiter Lauf darf nichts verdoppeln — der Import laeuft nach jedem Deploy erneut.
    $this->artisan('regions:import', ['--country' => ['us']])->assertSuccessful();

    expect(Region::where('country_id', $country->id)->count())->toBe(51);
});

it('imports several countries at once', function () {
    Country::factory()->create(['code' => 'us']);
    Country::factory()->create(['code' => 'de']);

    $this->artisan('regions:import', ['--country' => ['us', 'de']])->assertSuccessful();

    expect(Region::count())->toBe(67)
        ->and(Region::findByCountryCodeAndCode('de', 'by')?->name)->toBe('Bayern');
});

it('matches the country code case-insensitively', function () {
    // Produktion fuehrt "us", die lokalen Stammdaten "US" — beides muss greifen.
    Country::factory()->create(['code' => 'US']);

    $this->artisan('regions:import', ['--country' => ['us']])->assertSuccessful();

    expect(Region::count())->toBe(51);
});

/**
 * Zaehlt die CSV-Zeilen eines Landes direkt in der Quelldatei.
 *
 * Bewusst neben der erwarteten Zahl gepruaeft und nicht statt ihrer: stimmen Datenbank
 * und CSV ueberein, die feste Erwartung aber nicht mehr, hat sich die QUELLE geaendert
 * (ein `--refresh-source`-Lauf) — und das ist etwas anderes als ein kaputter Import.
 * Ohne die feste Zahl wuerde derselbe Test beides gleich gruen faerben.
 */
function csvRegionRows(string $countryCode): int
{
    $handle = fopen(base_path('database/data/regions.csv'), 'rb');
    fgetcsv($handle, escape: '');

    $rows = 0;

    try {
        while ($row = fgetcsv($handle, escape: '')) {
            if (mb_strtolower($row[2]) === $countryCode) {
                $rows++;
            }
        }
    } finally {
        fclose($handle);
    }

    return $rows;
}

/*
 * DoD P4: fuer jedes der acht Laender ist die Regionszahl gleich der Zeilenzahl in der
 * CSV. Die acht sind die Laender, die am 2026-08-25 wirklich in `countries` stehen —
 * der Plan nannte zusaetzlich `se` und `it`, die es dort nicht gibt.
 */
it('imports exactly the CSV rows for each of the eight countries', function (string $code, int $erwartet) {
    $country = Country::factory()->create(['code' => $code]);

    $this->artisan('regions:import', ['--country' => [$code]])->assertSuccessful();

    expect(csvRegionRows($code))->toBe($erwartet)
        ->and(Region::where('country_id', $country->id)->count())->toBe($erwartet);
})->with([
    'at' => ['at', 9],
    'ch' => ['ch', 26],
    'de' => ['de', 16],
    'es' => ['es', 19],
    'fr' => ['fr', 13],
    'gb' => ['gb', 4],
    'nl' => ['nl', 12],
    'us' => ['us', 51],
]);

/*
 * Der Befund vom 2026-08-25: `countries.code` ist nicht eindeutig. Im lokalen Bestand
 * stehen zwei Zeilen "CH" und neben "US" eine kleingeschriebene "us", die
 * "Deutschland" heisst. Der case-insensitive Vergleich traf beide, `keyBy()` behielt
 * still die LETZTE — die 51 US-Regionen waeren an die falsche Zeile gegangen, waehrend
 * die US-Staedte an der richtigen haengen.
 */
it('skips an ambiguous country code instead of guessing which row gets the regions', function () {
    $echt = Country::factory()->create(['code' => 'US', 'name' => 'Vereinigte Staaten']);
    $muell = Country::factory()->create(['code' => 'us', 'name' => 'Deutschland']);
    $deutschland = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland (echt)']);

    $this->artisan('regions:import', ['--country' => ['us', 'de']])
        ->expectsOutputToContain("Laendercode 'us' ist mehrdeutig")
        ->assertSuccessful();

    // Keine der beiden us-Zeilen bekommt etwas — das uebrige Land laeuft trotzdem durch.
    expect(Region::where('country_id', $echt->id)->count())->toBe(0)
        ->and(Region::where('country_id', $muell->id)->count())->toBe(0)
        ->and(Region::where('country_id', $deutschland->id)->count())->toBe(16);
});

it('fails when every requested country code is ambiguous', function () {
    Country::factory()->create(['code' => 'CH']);
    Country::factory()->create(['code' => 'ch']);

    $this->artisan('regions:import', ['--country' => ['ch']])
        ->expectsOutputToContain("Laendercode 'ch' ist mehrdeutig")
        ->assertFailed();

    expect(Region::count())->toBe(0);
});

it('warns about an unknown country instead of failing silently', function () {
    Country::factory()->create(['code' => 'de']);

    $this->artisan('regions:import', ['--country' => ['xx']])
        ->expectsOutputToContain("Land 'xx' existiert nicht")
        ->assertFailed();

    expect(Region::count())->toBe(0);
});

/*
 * ------------------------------------------------------------------------------------
 * P7: der Default ohne `--country`
 * ------------------------------------------------------------------------------------
 *
 * Vorher stand dort `['us']` — ein Platzhalter aus der Entwicklung. Eine Deploy-Zeile
 * `php artisan regions:import` hat damit 51 US-Regionen angelegt und kein einziges der
 * Laender, in denen dieses Portal betrieben wird. Der Fehler war unsichtbar, weil das
 * Kommando erfolgreich meldete.
 */

it('derives its default countries from the language files, not from a hardcoded list', function () {
    // Die Ableitung selbst: Schnittmenge aus lang/*.json und lang-country.allowed.
    $erwartet = collect(config('lang-country.languages'))
        ->only(collect(glob(lang_path('*.json')))->map(fn ($f) => pathinfo($f, PATHINFO_FILENAME))->all())
        ->flatMap(fn (array $sprache) => $sprache['countries'])
        ->intersect(config('lang-country.allowed'))
        ->map(fn (string $paar) => mb_strtolower(Str::after($paar, '-')))
        ->unique()->sort()->values()->all();

    expect(SupportedCountries::codes())->toBe($erwartet)
        // Und die Gegenprobe gegen einen leeren Vergleich: die Liste ist nicht leer und
        // enthaelt die Faelle, an denen die Regex-Erweiterung haengt (AT einstellig,
        // GB dreistellig).
        ->and($erwartet)->toContain('at', 'gb', 'de', 'us')
        ->and($erwartet)->not->toContain('fr'); // kein lang/fr.json — die Regel, nicht die Ausnahme
});

it('imports every derived country when called without an argument', function () {
    $codes = SupportedCountries::codes();

    /*
     * Bewusst OHNE Factory: `CountryFactory` zieht seine Zeile per `faker->unique()`
     * aus acht fest hinterlegten Laendern und wirft ab dem neunten Aufruf. Hier sind es
     * siebzehn — die Factory ist fuer diesen Fall schlicht nicht gebaut, und sie dafuer
     * umzubauen hiesse, dreissig bestehende Aufrufe anzufassen.
     */
    foreach ($codes as $code) {
        Country::query()->create([
            'code' => $code,
            'name' => mb_strtoupper($code),
            'language_codes' => [],
        ]);
    }

    $this->artisan('regions:import')->assertSuccessful();

    foreach ($codes as $code) {
        expect(Region::whereRelation('country', 'code', $code)->count())
            ->toBe(csvRegionRows($code), "Regionen fuer '{$code}' stimmen nicht mit der CSV ueberein");
    }

    // Nicht nur „irgendwas importiert": die Summe ist genau die der CSV-Zeilen.
    expect(Region::count())->toBe(collect($codes)->sum(csvRegionRows(...)));
});

it('no longer falls back to us alone', function () {
    Country::factory()->create(['code' => 'us']);
    Country::factory()->create(['code' => 'de']);

    $this->artisan('regions:import')->assertSuccessful();

    // Deutschland kommt mit, obwohl niemand danach gefragt hat — das ist der Unterschied.
    expect(Region::whereRelation('country', 'code', 'de')->count())->toBe(16)
        ->and(Region::whereRelation('country', 'code', 'us')->count())->toBe(51);
});

it('names the countries it is about to import', function () {
    Country::factory()->create(['code' => 'de']);

    $this->artisan('regions:import')
        ->expectsOutputToContain('aus den Sprachdateien abgeleitet')
        ->assertSuccessful();
});
