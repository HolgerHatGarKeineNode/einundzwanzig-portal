<?php

use App\Models\City;
use App\Models\Country;
use App\Models\CourseEvent;
use App\Models\Meetup;
use Illuminate\Support\Facades\Artisan;

const CLEANUP_CONFIRMATION = 'This permanently deletes cities on this database. Continue?';

it('does nothing on a dry-run', function () {
    $city = City::factory()->create();

    $this->artisan('places:cleanup')->assertExitCode(0);

    expect(City::query()->find($city->id))->not->toBeNull();
});

it('deletes a city nothing points at with --force', function () {
    $city = City::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation(CLEANUP_CONFIRMATION, 'yes')
        ->assertExitCode(0);

    expect(City::query()->find($city->id))->toBeNull();
});

/**
 * Steht nichts zum Loeschen an, faellt auch keine Rueckfrage — `expectsConfirmation`
 * fehlt hier absichtlich. Vorher fragte das Kommando als ALLERERSTE Anweisung, also
 * auch dann, wenn die Antwort „ja" gar nichts betroffen haette.
 */
it('keeps a city that still hosts course events', function () {
    $event = CourseEvent::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])->assertExitCode(0);

    expect(City::query()->find($event->city_id))->not->toBeNull();
});

/**
 * Der Nachweis fuer die Sperre, die mit `bitcoin_events` GEFALLEN ist.
 *
 * Bis zum Drop hielt `whereDoesntHave('bitcoinEvents')` eine Stadt fest, an der sonst
 * nichts hing — auf Produktion elf Stueck. Dieser Test haelt fest, dass genau das jetzt
 * NICHT mehr gilt: eine Stadt ohne Meetup und ohne Kurstermin ist loeschbar, egal was
 * frueher an ihr hing. Das ist die bewusste Entscheidung des Betreibers, keine Regression
 * — und sie muss messbar dastehen, sonst haelt der naechste Leser sie fuer einen Fehler.
 */
it('no longer knows a third guard beyond meetups and course events', function () {
    $city = City::factory()->create(['name' => 'Neusiedl am See']);

    $this->artisan('places:cleanup')
        ->expectsOutputToContain('Neusiedl am See')
        ->assertExitCode(0);

    expect(City::query()->find($city->id))->not->toBeNull();
});

it('keeps a city that still hosts meetups', function () {
    $meetup = Meetup::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])->assertExitCode(0);

    expect(City::query()->find($meetup->city_id))->not->toBeNull();
});

/**
 * Der Trockenlauf muss die Kandidaten NAMENTLICH nennen, nicht nur zaehlen.
 *
 * Eine blosse Anzahl ist keine Entscheidungsgrundlage: elf Staedte, deren einziger
 * Schutz mit `bitcoin_events` gefallen ist, sehen in einer Zahl genauso aus wie elf
 * Karteileichen. Gemessen wird deshalb Name UND Land, nicht die Zeilenzahl.
 */
it('names every candidate with its city and country in the dry-run', function () {
    $country = Country::factory()->create(['name' => 'Republik Testland']);
    $city = City::factory()->create(['name' => 'Uznach SG', 'country_id' => $country->id]);

    /*
     * Bewusst ueber Artisan::output() statt ueber mehrere expectsOutputToContain():
     * letztere setzen je eine Mockery-Erwartung auf EINEN doWrite-Aufruf. Stadt und Land
     * stehen in derselben Tabellenzeile, also erfuellt dieselbe Ausgabe beide — Mockery
     * verbraucht sie fuer die erste Erwartung, und die zweite meldet „nie ausgegeben",
     * obwohl der Text dasteht. Ein fail-open-Risiko in umgekehrter Richtung, aber
     * dieselbe Sorte Messfalle wie die Flux-Icons in P2.
     */
    expect(Artisan::call('places:cleanup'))->toBe(0);
    $ausgabe = Artisan::output();

    expect($ausgabe)
        ->toContain('Uznach SG')
        ->toContain('Republik Testland')
        ->toContain('Dry-run only. Re-run with --force to apply.');

    expect(City::query()->find($city->id))->not->toBeNull();
});

/**
 * Und ohne `--force` faellt keine Rueckfrage — ein Trockenlauf, der etwas bestaetigen
 * laesst, ist keiner. `expectsConfirmation` fehlt hier absichtlich: taeuchte die Frage
 * doch auf, bliebe das Kommando haengen und der Test wuerde rot.
 */
it('asks nothing at all without --force', function () {
    City::factory()->create();

    $this->artisan('places:cleanup')->assertExitCode(0);
});

/**
 * Die Rueckfrage steht hinter der Liste. Vorher war sie die erste Anweisung des
 * Kommandos — bestaetigt wurde also, bevor irgendetwas ausgegeben war. Dieser Test misst
 * die Reihenfolge ueber das, was sichtbar sein MUSS, wenn die Frage kommt.
 */
it('shows the candidates before it asks for confirmation', function () {
    $city = City::factory()->create(['name' => 'Wehingen']);

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsOutputToContain('Wehingen')
        ->expectsConfirmation(CLEANUP_CONFIRMATION, 'no')
        ->assertExitCode(1);

    expect(City::query()->find($city->id))->not->toBeNull();
});

it('aborts without deleting when the confirmation is declined', function () {
    $city = City::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation(CLEANUP_CONFIRMATION, 'no')
        ->assertExitCode(1);

    expect(City::query()->find($city->id))->not->toBeNull();
});
