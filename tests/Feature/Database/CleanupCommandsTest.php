<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = User::factory()->create(['nostr' => null]);
});

/** Meetup mit fest gewaehlter id — der Command bezieht sich auf genau diese. */
function meetupWithId(int $id, string $name, int $cityId, int $creatorId): Meetup
{
    // Bewusst mit Platzhalter anlegen und den echten Namen danach am Model vorbei
    // setzen: NormalizesText wuerde 'Einundzwanzig Ulm ' beim Speichern trimmen und
    // liefe dann in das unique auf meetups.name. Genau das ist kuenftig gewollt —
    // fuer diesen Test brauchen wir aber den Altbestand, wie er heute dasteht.
    $meetup = Meetup::factory()->create([
        'id' => $id,
        'city_id' => $cityId,
        'created_by' => $creatorId,
        'name' => 'platzhalter-'.$id,
    ]);

    DB::table('meetups')->where('id', $id)->update(['name' => $name]);

    return $meetup->fresh();
}

/** Die vom Betreiber am 26.08.2026 entschiedene Konstellation nachbauen. */
function entscheidungsLage(int $cityId, int $creatorId): void
{
    foreach ([
        [45, 'Einundzwanzig Mannheim'], [162, 'EINUNDZWANZIG Mannheim'],
        [232, 'Einundzwanzig Ulm'], [305, 'Einundzwanzig Ulm '], [63, 'Bitcoin Ulm'],
        [313, 'EINUNDZWANZIG LEIPZIG ₿'], [27, 'Bitcoin Leipzig'],
        [52, 'Einundzwanzig Karlsruhe'], [51, 'Bitcoin Karlsruhe'],
        [219, '21 Gießen'], [173, 'Einundzwanzig Gießen'],
        [14, 'Einundzwanzig Potsdam'], [244, 'Bitcoin Meetup - Einundzwanzig Potsdam'],
        [337, 'DELETE'],
    ] as [$id, $name]) {
        meetupWithId($id, $name, $cityId, $creatorId);
    }
}

it('changes nothing in a dry run', function () {
    entscheidungsLage($this->city->id, $this->creator->id);

    $this->artisan('meetups:cleanup-duplicates')->assertSuccessful();

    expect(Meetup::whereKey([162, 305, 63, 27, 51, 173, 244, 337])->count())->toBe(8);
});

it('aborts when a name no longer matches the decision', function () {
    entscheidungsLage($this->city->id, $this->creator->id);
    DB::table('meetups')->where('id', 162)->update(['name' => 'Etwas ganz anderes']);

    $this->artisan('meetups:cleanup-duplicates --force')->assertFailed();

    // Der Abbruch ist der Punkt: eine id allein ist kein sicherer Bezug.
    expect(Meetup::whereKey([162, 305, 63, 27, 51, 173, 244, 337])->count())->toBe(8);
});

it('merges and deletes exactly the decided meetups when forced', function () {
    entscheidungsLage($this->city->id, $this->creator->id);
    $termine = MeetupEvent::factory()->count(4)->create(['meetup_id' => 173]);
    $leader = User::factory()->create(['nostr' => 'npub1'.str_repeat('a', 58)]);
    Meetup::find(162)->promoteLeader($leader);

    $this->artisan('meetups:cleanup-duplicates --force')
        ->expectsConfirmation('Das loescht 8 Meetups unwiderruflich auf DIESER Datenbank. Weiter?', 'yes')
        ->assertSuccessful();

    expect(Meetup::whereKey([162, 305, 63, 27, 51, 173, 244, 337])->count())->toBe(0);
    expect(Meetup::whereKey([45, 232, 313, 52, 219, 14])->count())->toBe(6);

    // Die vier Giessener Termine haengen jetzt am Ueberlebenden, nicht im Nichts.
    foreach ($termine as $termin) {
        expect($termin->fresh()->meetup_id)->toBe(219);
    }

    // Der npub des geloeschten Mannheim-Eintrags erkennt seinen Organisator weiter.
    expect(Meetup::find(45)->leaders()->pluck('id')->all())->toContain($leader->id);
});

it('leaves the open cases alone', function () {
    entscheidungsLage($this->city->id, $this->creator->id);
    $teszt = meetupWithId(322, 'Teszt Bitcoin meetup', $this->city->id, $this->creator->id);

    $this->artisan('meetups:cleanup-duplicates --force')
        ->expectsConfirmation('Das loescht 8 Meetups unwiderruflich auf DIESER Datenbank. Weiter?', 'yes')
        ->assertSuccessful();

    // Budapest wurde noch nicht gefragt — der Eintrag bleibt.
    expect(Meetup::find($teszt->id))->not->toBeNull();
});

it('reports but does not write in the normalize dry run', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $this->creator->id]);
    DB::table('meetups')->where('id', $meetup->id)->update(['name' => 'Würzburg ', 'intro' => " Text\nZeile zwei "]);

    $this->artisan('db:normalize-text')->assertSuccessful();

    expect(DB::table('meetups')->where('id', $meetup->id)->value('name'))->toBe('Würzburg ');
});

it('writes both rules when the normalize run is forced', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $this->creator->id]);
    DB::table('meetups')->where('id', $meetup->id)->update([
        'name' => 'Einundzwanzig  Remstal ',
        'intro' => " Erste Zeile\n\nZweite Zeile ",
    ]);

    $this->artisan('db:normalize-text --force')->assertSuccessful();

    $zeile = DB::table('meetups')->where('id', $meetup->id)->first();
    expect($zeile->name)->toBe('Einundzwanzig Remstal');
    expect($zeile->intro)->toBe("Erste Zeile\n\nZweite Zeile");
});

it('does not fire change notifications for whitespace fixes', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $this->creator->id]);
    DB::table('meetups')->where('id', $meetup->id)->update(['name' => 'Sylt ']);
    $vorher = DB::table('api_changes')->count();

    $this->artisan('db:normalize-text --force')->assertSuccessful();

    // Der Resync-Feed ist fuer echte Aenderungen da — 540 Leerzeichen-Meldungen
    // wuerden ihn fuer 30 Tage zumuellen.
    expect(DB::table('api_changes')->count())->toBe($vorher);
});

it('runs without a terminal when forced', function () {
    entscheidungsLage($this->city->id, $this->creator->id);

    // Auf dem Server gibt es kein Terminal. Ohne die isInteractive()-Pruefung
    // gaebe confirm() den Default false zurueck und der Lauf braeche stumm ab —
    // gruener Exit-Code, nichts passiert.
    $this->artisan('meetups:cleanup-duplicates --force --no-interaction')->assertSuccessful();

    expect(Meetup::whereKey([162, 305, 63, 27, 51, 173, 244, 337])->count())->toBe(0);
});

it('keeps going when a trimmed name collides with an existing one', function () {
    $sauber = Meetup::factory()->create([
        'city_id' => $this->city->id, 'created_by' => $this->creator->id, 'name' => 'platzhalter-a',
    ]);
    $unsauber = Meetup::factory()->create([
        'city_id' => $this->city->id, 'created_by' => $this->creator->id, 'name' => 'platzhalter-b',
    ]);
    $harmlos = Meetup::factory()->create([
        'city_id' => $this->city->id, 'created_by' => $this->creator->id, 'name' => 'platzhalter-c',
    ]);
    DB::table('meetups')->where('id', $sauber->id)->update(['name' => 'Einundzwanzig Ulm']);
    DB::table('meetups')->where('id', $unsauber->id)->update(['name' => 'Einundzwanzig Ulm ']);
    DB::table('meetups')->where('id', $harmlos->id)->update(['name' => 'Sylt ']);

    $this->artisan('db:normalize-text --force')
        ->expectsOutputToContain('kollidiert getrimmt')
        ->assertSuccessful();

    // Die Kollision bleibt stehen und wird gemeldet, der Rest wird korrigiert.
    expect(DB::table('meetups')->where('id', $unsauber->id)->value('name'))->toBe('Einundzwanzig Ulm ');
    expect(DB::table('meetups')->where('id', $harmlos->id)->value('name'))->toBe('Sylt');
});
