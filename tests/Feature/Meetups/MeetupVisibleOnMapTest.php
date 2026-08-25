<?php

/*
|--------------------------------------------------------------------------
| P2 Schritt 1 — `visible_on_map` im Bearbeiten-Formular
|
| Die Property gab es schon (edit.blade.php:63), mount() lud sie (:287) — es
| fehlten die beiden Enden: kein Schalter im Template und kein Eintrag in
| validate(). `update($validated)` sieht nur, was validate() zurueckgibt, also
| verschwand jede Aenderung lautlos. Beide Enden brauchen einen eigenen
| Nachweis, sonst haelt der Test auch dann, wenn nur eines wieder faellt.
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use Livewire\Livewire;

/*
| Warum `(bool)` vor jeder Erwartung steht: `meetups.visible_on_map` fehlt in
| `Meetup::$casts`, waehrend `is_active`, `rsvp_enabled` und `attendees_public`
| dort als 'boolean' stehen (Meetup.php:63-73). Das Feld kommt deshalb als 0/1
| zurueck, nicht als false/true. Der Cast nachzuziehen aendert die Ausgabe der
| oeffentlichen API mit — das ist eine eigene Entscheidung und gehoert nicht in
| diese Phase. Der Cast macht diese Tests unabhaengig davon: sie bleiben gruen,
| egal welche der beiden Formen das Modell liefert.
*/

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

/**
 * Ein Meetup, das der angemeldete Nutzer bearbeiten darf.
 */
function meetupOwnedBy(int $cityId, bool $visibleOnMap): Meetup
{
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $cityId,
        'created_by' => $creator->id,
        'community' => 'einundzwanzig',
        'visible_on_map' => $visibleOnMap,
    ]);
    $meetup->users()->attach($creator);

    return $meetup;
}

it('switches visible_on_map off and the value is in the database afterwards', function () {
    $meetup = meetupOwnedBy($this->city->id, true);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertSet('visible_on_map', true)
        ->set('visible_on_map', false)
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect((bool) $meetup->fresh()->visible_on_map)->toBeFalse();
});

it('switches visible_on_map back on', function () {
    $meetup = meetupOwnedBy($this->city->id, false);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertSet('visible_on_map', false)
        ->set('visible_on_map', true)
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect((bool) $meetup->fresh()->visible_on_map)->toBeTrue();
});

it('leaves visible_on_map alone when the form saves without touching it', function () {
    // Die Gegenprobe zum Schalter: das Feld darf beim Speichern anderer Werte nicht
    // auf seinen Property-Default zurueckfallen.
    $meetup = meetupOwnedBy($this->city->id, false);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Umbenannt ohne Kartenschalter')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect((bool) $meetup->fresh()->visible_on_map)->toBeFalse();
});

it('renders a wire:model element for visible_on_map in the edit form', function () {
    // Das zweite Ende. Ohne diese Zeile bliebe der Test oben gruen, waehrend im
    // Formular kein Bedienelement mehr steht — `set()` geht an der gerenderten
    // Komponente vorbei und wuerde das nie bemerken.
    $meetup = meetupOwnedBy($this->city->id, true);

    $html = Livewire::test('meetups.edit', ['meetup' => $meetup])->html();

    expect(substr_count($html, 'wire:model="visible_on_map"'))->toBe(1);
});
