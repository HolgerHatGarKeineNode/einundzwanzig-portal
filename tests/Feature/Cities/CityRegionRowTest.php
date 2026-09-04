<?php

/*
|--------------------------------------------------------------------------
| P2 Schritt 2+3 — die Region-Zeile hat immer genau eine Auspraegung
|
| Vorher verschwand das Feld ganz, sobald der Regionen-Katalog des Landes leer
| war. Mit 6 von 8 Laendern ohne Import ist das der Normalfall, nicht der Rand:
| wer die Zeile nicht sieht, haelt das Feld fuer nicht vorhanden statt fuer noch
| nicht befuellbar. Und die `region_id`-Regel (edit.blade.php:163) hatte fuer
| ihre Meldung ueberhaupt keinen Ort.
|
| MESSHINWEIS: Flux inlined jedes Icon als rohes SVG — der Name "lock-closed"
| steht danach NIRGENDS im Dokument. Ein Test, der darauf greppt, meldet
| „kein Schloss" auch dort, wo eines steht (fail-open, hier gemessen). Deshalb
| traegt jedes Zustands-Icon ein `data-region-icon`, und der Test unten faehrt
| eine Positivkontrolle: derselbe Ausdruck MUSS das Schloss im gesperrten Fall
| finden, sonst ist die Sonde kaputt und nicht die Seite.
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Livewire\Livewire;

/**
 * Die eine Region-Zeile aus dem gerenderten Formular.
 *
 * Regex statt DOM-Parser: `<ui-field>` ist ein Custom Element, und dass die Zeile
 * genau einmal vorkommt, prueft der erste Test hier ausdruecklich.
 */
function regionRow(string $html): string
{
    preg_match('/<ui-field[^>]*data-region-row="[^"]*"[\s\S]*?<\/ui-field>/', $html, $treffer);

    return $treffer[0] ?? '';
}

/**
 * Dieselbe Zeile ohne ihr oeffnendes `<ui-field …>`-Tag.
 *
 * Flux haengt an das Element eine Klassenliste mit bedingten Tailwind-Varianten wie
 * `[&:has([data-flux-control][disabled])>[data-flux-label]]:opacity-50`. Darin stehen
 * die Woerter „disabled" und „opacity-50" als SELEKTOR, nicht als Zustand — eine
 * Abwesenheitsprobe auf der ganzen Zeile schlaegt daran an, obwohl nichts deaktiviert
 * und nichts gedimmt ist. Gemessen, nicht vermutet: genau so ist der Test unten beim
 * ersten Lauf falsch rot geworden.
 */
function regionRowInner(string $html): string
{
    return (string) preg_replace('/^<ui-field[^>]*>/', '', regionRow($html));
}

/**
 * Die acht Laender aus P4 des Plans. Heute traegt keines davon einen Katalog —
 * genau deshalb war die Zeile fuer sechs von ihnen gar nicht da.
 */
dataset('die acht laender', ['de', 'at', 'ch', 'nl', 'se', 'it', 'es', 'us']);

/*
|--------------------------------------------------------------------------
| Genau eine Zeile — ueber alle acht Laender, mit und ohne Katalog
|--------------------------------------------------------------------------
*/

it('renders exactly one region row in the edit form for a country without a region catalogue',
    function (string $code) {
        $country = Country::factory()->create(['code' => $code]);
        $owner = User::factory()->create();
        $city = City::factory()->create([
            'country_id' => $country->id,
            'created_by' => $owner->id,
            'region_id' => null,
        ]);

        $html = Livewire::actingAs($owner)->test('cities.edit', ['city' => $city])->html();

        expect(substr_count($html, 'data-region-row'))->toBe(1)
            ->and($html)->toContain('data-region-row="no-catalog"');
    })->with('die acht laender');

it('renders exactly one region row in the edit form for a country with a region catalogue',
    function (string $code) {
        $country = Country::factory()->create(['code' => $code]);
        Region::factory()->create(['country_id' => $country->id]);
        $owner = User::factory()->create();
        $city = City::factory()->create([
            'country_id' => $country->id,
            'created_by' => $owner->id,
            'region_id' => null,
        ]);

        $html = Livewire::actingAs($owner)->test('cities.edit', ['city' => $city])->html();

        expect(substr_count($html, 'data-region-row'))->toBe(1)
            ->and($html)->toContain('data-region-row="select"');
    })->with('die acht laender');

it('renders exactly one region row in the create form for all eight countries', function (string $code) {
    $country = Country::factory()->create(['code' => $code]);
    $this->actingAs(User::factory()->create());

    $html = Livewire::test('cities.create')
        ->set('country_id', $country->id)
        ->html();

    expect(substr_count($html, 'data-region-row'))->toBe(1)
        ->and($html)->toContain('data-region-row="no-catalog"');
})->with('die acht laender');

/*
|--------------------------------------------------------------------------
| Kein Schloss, wo keine Berechtigung im Weg steht — mit Positivkontrolle
|--------------------------------------------------------------------------
*/

it('shows the lock in the region row when the identity is locked and a region is set', function () {
    // POSITIVKONTROLLE fuer den Test darunter: findet dieser Ausdruck hier kein
    // Schloss, misst der Negativtest nichts und ist wertlos.
    $country = Country::factory()->create(['code' => 'de']);
    $region = Region::factory()->create(['country_id' => $country->id]);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'region_id' => $region->id,
        'created_by' => User::factory()->create()->id,
    ]);

    $html = Livewire::actingAs(User::factory()->create())->test('cities.edit', ['city' => $city])->html();
    $zeile = regionRow($html);

    expect($html)->toContain('data-region-row="locked-value"')
        ->and($zeile)->toContain('data-region-icon="lock-closed"')
        ->and($zeile)->toContain($region->name);
});

it('never shows a lock in the region row when the country has no region catalogue', function () {
    $country = Country::factory()->create(['code' => 'at']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'region_id' => null,
        'created_by' => User::factory()->create()->id,
    ]);

    // Gesperrt: ein Fremder ohne Steward-Recht. Die Ursache ist trotzdem der leere
    // Katalog, nicht die Berechtigung — ein Schloss wuerde hier luegen.
    $html = Livewire::actingAs(User::factory()->create())->test('cities.edit', ['city' => $city])->html();
    $zeile = regionRow($html);

    expect($html)->toContain('data-region-row="no-catalog"')
        ->and($zeile)->toContain('data-region-icon="map"')
        ->and($zeile)->not->toContain('data-region-icon="lock-closed"')
        ->and($zeile)->toContain(__('No regions have been imported for this country yet.'));
});

it('shows the lock with a not-set marker when the identity is locked and no region is set', function () {
    $country = Country::factory()->create(['code' => 'de']);
    Region::factory()->create(['country_id' => $country->id]);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'region_id' => null,
        'created_by' => User::factory()->create()->id,
    ]);

    $html = Livewire::actingAs(User::factory()->create())->test('cities.edit', ['city' => $city])->html();
    $zeile = regionRow($html);

    expect($html)->toContain('data-region-row="locked-empty"')
        ->and($zeile)->toContain('data-region-icon="lock-closed"')
        ->and($zeile)->toContain('aria-describedby="identity-lock"')
        ->and($zeile)->toContain(__('— not set'));
});

/*
|--------------------------------------------------------------------------
| Kein Land gewaehlt — „fuer dieses Land gibt es keine Regionen" waere falsch
|--------------------------------------------------------------------------
*/

it('asks for a country first instead of blaming an empty catalogue in the create form', function () {
    Country::factory()->create(['code' => 'de']);
    $this->actingAs(User::factory()->create());

    $html = Livewire::test('cities.create')
        ->set('country_id', null)
        ->html();
    $zeile = regionRow($html);

    expect(substr_count($html, 'data-region-row'))->toBe(1)
        ->and($html)->toContain('data-region-row="no-country"')
        ->and($zeile)->toContain('Wähl zuerst ein Land.')
        ->and($zeile)->not->toContain(__('No regions have been imported for this country yet.'))
        ->and($zeile)->not->toContain('data-region-icon="lock-closed"');
});

it('asks for a country first in the edit form when the country select is cleared', function () {
    $country = Country::factory()->create(['code' => 'de']);
    $owner = User::factory()->create();
    $city = City::factory()->create([
        'country_id' => $country->id,
        'created_by' => $owner->id,
        'region_id' => null,
    ]);

    $html = Livewire::actingAs($owner)->test('cities.edit', ['city' => $city])
        ->set('country_id', null)
        ->html();

    expect(substr_count($html, 'data-region-row'))->toBe(1)
        ->and($html)->toContain('data-region-row="no-country"')
        ->and(regionRow($html))->toContain('Wähl zuerst ein Land.');
});

/*
|--------------------------------------------------------------------------
| Die region_id-Meldung hat einen Ort — in JEDEM Zustand der Zeile
|--------------------------------------------------------------------------
*/

it('places the region_id error message inside the region row when a catalogue exists', function () {
    $country = Country::factory()->create(['code' => 'de']);
    Region::factory()->create(['country_id' => $country->id]);
    $owner = User::factory()->create();
    $city = City::factory()->create([
        'country_id' => $country->id,
        'created_by' => $owner->id,
        'region_id' => null,
    ]);

    $komponente = Livewire::actingAs($owner)->test('cities.edit', ['city' => $city])
        ->set('region_id', 999999)
        ->call('updateCity')
        ->assertHasErrors('region_id');

    $meldung = $komponente->errors()->first('region_id');

    expect($meldung)->not->toBeEmpty()
        ->and(regionRow($komponente->html()))->toContain($meldung);
});

it('places the region_id error message inside the region row when the catalogue is empty', function () {
    // Der Fall, den es vorher gar nicht geben konnte: ohne Katalog wurde die Zeile
    // nicht gerendert, also hatte die Meldung keinen Ort. Die ungueltige ID kommt
    // hier aus einem anderen Land — genau das, was `Rule::exists(...)->where()` abwehrt.
    $ohneKatalog = Country::factory()->create(['code' => 'at']);
    $mitKatalog = Country::factory()->create(['code' => 'us']);
    $fremdeRegion = Region::factory()->create(['country_id' => $mitKatalog->id]);
    $owner = User::factory()->create();
    $city = City::factory()->create([
        'country_id' => $ohneKatalog->id,
        'created_by' => $owner->id,
        'region_id' => null,
    ]);

    $komponente = Livewire::actingAs($owner)->test('cities.edit', ['city' => $city])
        ->set('region_id', $fremdeRegion->id)
        ->call('updateCity')
        ->assertHasErrors('region_id');

    $meldung = $komponente->errors()->first('region_id');
    $zeile = regionRow($komponente->html());

    expect($meldung)->not->toBeEmpty()
        ->and($zeile)->toContain('data-region-row="no-catalog"')
        ->and($zeile)->toContain($meldung);
});

/*
|--------------------------------------------------------------------------
| Kein disabled-Select fuer die Leerfaelle
|--------------------------------------------------------------------------
*/

it('never renders a disabled control for an empty region catalogue', function () {
    // Ein `disabled`-Select dimmt in Flux per opacity-50: der Label-Kontrast faellt
    // im Hellmodus von 15,13:1 auf 3,09:1 und der Tab-Stopp entfaellt ersatzlos.
    $country = Country::factory()->create(['code' => 'ch']);
    $owner = User::factory()->create();
    $city = City::factory()->create([
        'country_id' => $country->id,
        'created_by' => $owner->id,
        'region_id' => null,
    ]);

    $html = Livewire::actingAs($owner)->test('cities.edit', ['city' => $city])->html();

    expect(regionRow($html))->toContain('data-region-row="no-catalog"')
        // Kontrolle, dass der Schnitt oben wirklich schneidet: das Wort steht in Flux'
        // Bedingungsklassen am aeusseren Tag und darf danach weg sein.
        ->and(regionRow($html))->toContain('opacity-50')
        ->and(regionRowInner($html))->not->toContain('opacity-50')
        // Und erst jetzt die eigentliche Aussage.
        ->and(regionRowInner($html))->not->toContain('disabled')
        ->and(regionRowInner($html))->not->toContain('<select');
});

/*
|--------------------------------------------------------------------------
| Der Schreibpfad bleibt unveraendert
|--------------------------------------------------------------------------
*/

it('still stores a region through the listbox state', function () {
    $country = Country::factory()->create(['code' => 'us']);
    $region = Region::factory()->create(['country_id' => $country->id]);
    $owner = User::factory()->create();
    $city = City::factory()->create([
        'country_id' => $country->id,
        'created_by' => $owner->id,
        'region_id' => null,
    ]);

    Livewire::actingAs($owner)->test('cities.edit', ['city' => $city])
        ->set('region_id', $region->id)
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($city->fresh()->region_id)->toBe($region->id);
});

/*
|--------------------------------------------------------------------------
| Das Land aus der URL — mit gross gespeichertem Laendercode
|--------------------------------------------------------------------------
|
| Jeder create-Fall oben faehrt `Livewire::test('cities.create')` und setzt
| `country_id` gleich danach selbst. `mount()` laeuft dabei zwar, sein Ergebnis
| wird aber sofort ueberschrieben — deshalb konnte keiner dieser Faelle rot
| werden, als die Vorauswahl in `mount()` den Laendercode case-sensitiv verglich.
| Mit gespeichertem 'DE' und der Route /de/city-create blieb `country_id` null,
| und das Formular verlangte „Wähl zuerst ein Land." fuer ein Land, das in der
| URL steht.
|
| Dieser Fall geht darum ueber die ROUTE statt ueber die Komponente und speichert
| den Code so, wie CountryFactory ihn von sich aus schreibt: gross. `no-catalog`
| ist der Beleg, dass das Land aufgeloest wurde — die Zeile begruendet sich dann
| mit dem leeren Regionen-Katalog und nicht mehr mit einer fehlenden Landeswahl.
|
*/

it('preselects the country from the url when its stored code is uppercase', function () {
    Country::factory()->create(['code' => 'DE']);

    $html = $this->actingAs(User::factory()->create())
        ->get('/de/city-create')
        ->assertOk()
        ->getContent();

    // Den Zustand als WERT lesen, nicht als Vorhandensein eines Teilstrings: ein
    // `not->toContain(...)` auf dem ganzen Dokument meldet im Fehlerfall 26.000
    // Zeilen HTML und nennt nicht, was statt dessen dasteht. So steht im roten
    // Lauf `-'no-catalog' +'no-country'`, und das ist die ganze Diagnose.
    preg_match('/data-region-row="([^"]*)"/', $html, $zustand);

    expect(substr_count($html, 'data-region-row'))->toBe(1)
        ->and($zustand[1] ?? null)->toBe('no-catalog')
        // Und dasselbe noch einmal am sichtbaren Symptom, das der Melder gesehen hat.
        ->and(regionRow($html))->not->toContain('Wähl zuerst ein Land.');
});
