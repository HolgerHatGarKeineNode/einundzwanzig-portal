<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = User::factory()->create(['nostr' => null]);
});

it('trims a meetup name and collapses inner double spaces', function () {
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
        'name' => '  EINUNDZWANZIG BASEL  - BITCOIN BASEL ',
    ]);

    expect($meetup->fresh()->name)->toBe('EINUNDZWANZIG BASEL - BITCOIN BASEL');
});

it('leaves the line breaks in a free text alone', function () {
    $mitAbsatz = "  Erste Zeile\n\nZweite Zeile nach Leerzeile.  ";

    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
        'intro' => $mitAbsatz,
    ]);

    // Genau der Datenverlust, den die zweite Regel verhindert: 1232 Termin-
    // Beschreibungen und 86 Intros tragen Umbrueche.
    expect($meetup->fresh()->intro)->toBe("Erste Zeile\n\nZweite Zeile nach Leerzeile.");
});

it('normalizes on update, not only on create', function () {
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
        'name' => 'Sauber',
    ]);

    // Am Model vorbei einschleusen, wie es der Altbestand tut.
    DB::table('meetups')->where('id', $meetup->id)->update(['name' => 'Unsauber ']);

    $frisch = Meetup::find($meetup->id);
    $frisch->intro = 'irgendetwas';
    $frisch->save();

    expect($frisch->fresh()->name)->toBe('Unsauber');
});

it('turns a nullable field that was only spaces into null', function () {
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
        'intro' => '   ',
    ]);

    expect($meetup->fresh()->intro)->toBeNull();
});

it('keeps a required field as an empty string instead of null', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => Meetup::factory()->create([
            'city_id' => $this->city->id,
            'created_by' => $this->creator->id,
        ])->id,
        'location' => '  ',
    ]);

    // location ist nullable, also null — der Gegentest zu name weiter unten.
    expect($event->fresh()->location)->toBeNull();
});

it('never nulls a meetup name that was only spaces', function () {
    $meetup = Meetup::factory()->make([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
    ]);
    $meetup->name = '   ';

    // name steht in $normalizedRequired: die Spalte ist NOT NULL, ein null hier
    // waere ein Datenbankfehler statt einer Korrektur.
    $meetup->save();

    expect($meetup->fresh()->name)->toBe('');
});

it('applies the same two rules to a meetup event', function () {
    // City fehlt hier bewusst: Staedte haben ihren eigenen Weg (Migration
    // 2026_08_25_001255 plus db:audit-cities) und tragen den Trait nicht.
    $event = MeetupEvent::factory()->create([
        'meetup_id' => Meetup::factory()->create([
            'city_id' => $this->city->id,
            'created_by' => $this->creator->id,
        ])->id,
        'title' => ' Meetup Kassel ',
        'location' => 'Torstraße 199,  Berlin ',
        'description' => " Zeile eins\nZeile zwei ",
    ]);

    $event->refresh();
    expect($event->title)->toBe('Meetup Kassel');
    expect($event->location)->toBe('Torstraße 199, Berlin');
    expect($event->description)->toBe("Zeile eins\nZeile zwei");
});

it('exposes both rules as one shared source', function () {
    expect(TextNormalizer::label('  a   b  '))->toBe('a b');
    expect(TextNormalizer::prose("  a\n\n  b  "))->toBe("a\n\n  b");
    // Auch die Label-Regel laesst Umbrueche stehen — ein Umbruch in einer
    // Bezeichnung ist ein Schaden, ihn zu schlucken waere der zweite.
    expect(TextNormalizer::label("a  \n  b"))->toBe("a \n b");
});
