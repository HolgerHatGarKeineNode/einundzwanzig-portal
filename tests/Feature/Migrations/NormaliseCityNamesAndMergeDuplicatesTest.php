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
 * P2-d — der Fund, der zur Zusage wurde.
 *
 * Diese Stelle trug bis zum 2026-08-25 einen Kommentarblock statt eines Tests: der
 * `test-engineer` hatte gemessen, dass `mergeDuplicatesAfterTrim()` acht REALE
 * Neuenkirchen zu einer Zeile zusammenlegt ("before" => [1..8], "after" => [1]), durfte
 * den Produktivcode aber nicht anfassen. Der Koordinator hat die Migration danach
 * korrigiert — `COUNT(DISTINCT name) > 1` —, und aus dem Fund wurde der Test darunter.
 *
 * Er steht hier und nicht bei den anderen, weil er die teuerste Zusage dieser Migration
 * ist: sieben stille Loeschungen echter Gemeinden, deren Meetups danach an der Zeile mit
 * der kleinsten id haengen. Ein "erfolgreiches Aufraeumen", das keins ist.
 */
it('leaves eight genuine same-name places untouched — only whitespace collisions merge', function () {
    $country = Country::factory()->create();
    $vorher = neuenkirchenCities($country);

    // Der echte Trim-Fall daneben, damit der Test auch beweist, dass die Migration
    // ueberhaupt noch etwas tut — sonst waere eine Migration, die gar nichts merged,
    // ebenfalls gruen.
    $verschmutzt = City::factory()->create(['name' => 'Offenburg ', 'country_id' => $country->id, 'slug' => null]);
    $sauber = City::factory()->create(['name' => 'Offenburg', 'country_id' => $country->id, 'slug' => null]);

    (require base_path('database/migrations/2026_08_25_001255_normalise_city_names_and_merge_duplicates.php'))->up();

    expect(City::where('name', 'Neuenkirchen')->count())->toBe(8)
        ->and(City::whereIn('id', $vorher->pluck('id'))->count())->toBe(8)
        ->and(City::where('name', 'Offenburg')->count())->toBe(1)
        ->and(City::whereRaw('name <> TRIM(name)')->count())->toBe(0);

    // Die kleinere id ueberlebt auch hier.
    expect(City::find(min($verschmutzt->id, $sauber->id)))->not->toBeNull()
        ->and(City::find(max($verschmutzt->id, $sauber->id)))->toBeNull();
});
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
