<?php

/*
|--------------------------------------------------------------------------
| staedte-identitaet P2 — Migration 2026_08_25_001255
|--------------------------------------------------------------------------
|
| RefreshDatabase faehrt diese Migration bereits beim Testaufbau — aber gegen
| eine LEERE cities-Tabelle, also ohne dass es dort etwas zusammenzulegen gibt.
| Um den Merge-Zweig selbst zu pruefen, wird die Migrationsdatei direkt
| requiret und ihr up() ein zweites Mal aufgerufen, diesmal gegen Fixture-Daten,
| die vorher per Model/Factory angelegt wurden (siehe Plan-Hinweis: ein zweiter
| `migrate`-Aufruf taete nichts, da Laravel die Migration schon als "ran" fuehrt).
|--------------------------------------------------------------------------
*/

use App\Models\ApiChange;
use App\Models\BitcoinEvent;
use App\Models\City;
use App\Models\Country;
use App\Models\CourseEvent;
use App\Models\Meetup;
use Illuminate\Support\Facades\DB;

function runNormaliseCityNamesMigration(): void
{
    (require base_path('database/migrations/2026_08_25_001255_normalise_city_names_and_merge_duplicates.php'))->up();
}

// P2-a + P2-b + P2-c — Der Offenburg-Fall exakt nachgebaut: die AELTERE (kleinere id),
// verschmutzte Zeile traegt ein Meetup; die JUENGERE, saubere traegt BitcoinEvent,
// CourseEvent und einen api_changes-Eintrag (keine FK). Nach dem Merge muss die
// kleinere id ueberleben und ALLE vier Referenzen tragen — nichts geht verloren.
it('merges the dirty older row, keeps every referencing row, and drags the FK-less api_changes along', function () {
    $country = Country::factory()->create();

    $dirty = City::factory()->create(['name' => 'Offenburg ', 'country_id' => $country->id]);
    $clean = City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id]);

    expect($dirty->id)->toBeLessThan($clean->id); // Reihenfolge der Erzeugung == Reihenfolge der id

    $meetup = Meetup::factory()->create(['city_id' => $dirty->id]);
    $bitcoinEvent = BitcoinEvent::factory()->create(['city_id' => $clean->id]);
    $courseEvent = CourseEvent::factory()->create(['city_id' => $clean->id]);
    $apiChange = ApiChange::factory()->create(['city_id' => $clean->id]);

    $citiesBefore = City::count();
    $meetupsBefore = Meetup::count();
    $bitcoinEventsBefore = BitcoinEvent::count();
    $courseEventsBefore = CourseEvent::count();
    $apiChangesBefore = ApiChange::count();

    runNormaliseCityNamesMigration();

    // P2-a — nichts verloren: cities minus 1 (der Merge), alle referenzierenden
    // Tabellen unveraendert in ihrer Zeilenzahl.
    expect(City::count())->toBe($citiesBefore - 1)
        ->and(Meetup::count())->toBe($meetupsBefore)
        ->and(BitcoinEvent::count())->toBe($bitcoinEventsBefore)
        ->and(CourseEvent::count())->toBe($courseEventsBefore)
        ->and(ApiChange::count())->toBe($apiChangesBefore);

    // P2-b — die KLEINERE id ueberlebt, obwohl sie die verschmutzte war.
    expect(City::find($dirty->id))->not->toBeNull()
        ->and(City::find($clean->id))->toBeNull()
        ->and(City::find($dirty->id)->name)->toBe('Offenburg');

    // Beide vorher getrennten Datensaetze haengen jetzt an der ueberlebenden Zeile.
    expect($meetup->fresh()->city_id)->toBe($dirty->id)
        ->and($bitcoinEvent->fresh()->city_id)->toBe($dirty->id)
        ->and($courseEvent->fresh()->city_id)->toBe($dirty->id);

    // P2-c — api_changes.city_id (KEIN Fremdschluessel) wurde mitgezogen, zeigt also
    // nicht still auf die geloeschte id.
    expect($apiChange->fresh()->city_id)->toBe($dirty->id);

    // Bestandteil derselben Zusage: kein Name mit aeusserem Leerzeichen bleibt stehen.
    expect(DB::table('cities')->whereRaw('name <> TRIM(name)')->count())->toBe(0);
});

// P2-b, isoliert: die verschmutzte Zeile ist die kleinere id UND traegt am Ende den
// Namen — der Merge loescht die groessere, nicht die "sauberere".
it('keeps the smaller id as the survivor even though it is the dirty row', function () {
    $country = Country::factory()->create();
    $dirty = City::factory()->create(['name' => 'Offenburg ', 'country_id' => $country->id]);
    $clean = City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id]);

    runNormaliseCityNamesMigration();

    $survivors = City::query()->where('country_id', $country->id)->get();

    expect($survivors)->toHaveCount(1)
        ->and($survivors->first()->id)->toBe($dirty->id)
        ->and($survivors->first()->id)->not->toBe($clean->id);
});

/*
|--------------------------------------------------------------------------
| P2-d — Fund, nicht Test: die Migration merged acht REALE Neuenkirchen zu einem.
|--------------------------------------------------------------------------
|
| `mergeDuplicatesAfterTrim()` gruppiert nach (country_id, LOWER(TRIM(name)))
| und legt JEDE Gruppe mit COUNT(*) > 1 zusammen — ohne zu pruefen, ob die
| Kollision UEBERHAUPT erst durch das Trimmen entsteht. Acht Zeilen, die alle
| bereits byte-identisch "Neuenkirchen" heissen (keine einzige mit
| Leerzeichen-Suffix), bilden dieselbe Gruppe wie 'Offenburg'/'Offenburg ' —
| und werden genauso zusammengelegt. Das widerspricht woertlich dem Docblock
| der Migration selbst ("Acht Neuenkirchen in Niedersachsen bleiben acht
| Neuenkirchen") und der DoD dieser Phase (P2-d).
|
| Belegt per vendor/bin/pest --agent (2026-08-25) mit der Fixture aus
| tests/Pest.php::neuenkirchenCities() (acht reale, unterschiedliche
| Landkreise, alle country_id gleich, alle Namen exakt "Neuenkirchen"):
|
|   "before" => [1,2,3,4,5,6,7,8]
|   "after"  => [1]
|   "total_named_neuenkirchen" => 1
|
| Sieben von acht echten Gemeinden verschwinden, ihre etwaigen Meetups/Events
| haengen danach an der Gemeinde mit der kleinsten id — ein "erfolgreiches
| Aufraeumen", das keins ist (Risiko 7 im Plan).
|
| Fix-Kandidat (EINER, nicht umgesetzt — Produktivcode wird von dieser
| Testreihe nicht angefasst): die Gruppe darf nur zusammengelegt werden, wenn
| die Kollision durch das Trimmen ENTSTEHT — also mindestens eine Zeile der
| Gruppe ein aeusseres Leerzeichen traegt. Etwa eine zusaetzliche Bedingung in
| `mergeDuplicatesAfterTrim()`:
| `havingRaw('COUNT(*) > 1 AND SUM(CASE WHEN name <> TRIM(name) THEN 1 ELSE 0 END) > 0')`.
| Eine Gruppe aus ausschliesslich bereits sauberen, identischen Namen (echte
| Homonyme) faellt damit durch und bleibt unangetastet.
|
| Diagnosekosten fuer eine vollstaendige Absicherung (Migration korrigieren +
| Test von "Fund" auf gruene Zusage umstellen): klein, geschaetzt < 30 Min —
| die Ursache ist bereits eindeutig lokalisiert, es fehlt nur die Entscheidung
| des Koordinators, den Produktivcode anzufassen.
|--------------------------------------------------------------------------
*/

// P2-e — elf kollisionsfreie Trims laufen durch, ohne dass irgendetwas zusammengelegt
// wird: dieselben Namen wie im Plan gemessen (Produktions-Stichprobe 2026-08-24).
it('trims eleven collision-free names without merging or losing any row', function () {
    $country = Country::factory()->create();

    $names = [
        'Schweinfurt ', 'Neubrandenburg ', 'Spreewald ', 'Uelzen ', 'Ludwigsburg ',
        'Sylt ', 'Wehingen ', 'Travemünde ', 'Kirchheim Teck ', 'Region Strohgäu ',
        'Hemishofen ',
    ];

    $cities = collect($names)->map(
        fn (string $name) => City::factory()->create(['name' => $name, 'country_id' => $country->id])
    );

    $countBefore = City::count();

    runNormaliseCityNamesMigration();

    expect(City::count())->toBe($countBefore);

    $cities->each(function (City $city) {
        expect($city->id)->toBe(City::find($city->id)?->id) // dieselbe Zeile, nicht neu angelegt
            ->and($city->fresh()->name)->toBe(trim($city->name));
    });

    expect(DB::table('cities')->whereRaw('name <> TRIM(name)')->count())->toBe(0);
});
