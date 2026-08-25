<?php

use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Die vier Schalter eines Meetups verlassen die API in derselben Form.
 *
 * `visible_on_map` fehlte in `Meetup::$casts`, waehrend `is_active`, `rsvp_enabled` und
 * `attendees_public` dort standen. Ein Konsument bekam also `0`/`1` fuer das eine und
 * `false`/`true` fuer die anderen drei — aus keinem Grund ausser dem, dass jemand beim
 * Ergaenzen eine Zeile uebersehen hatte.
 *
 * Der Test nagelt die AUSGABEFORM fest, nicht nur den Wert: `assertJsonPath` vergleicht
 * strikt, `0` bestuende die Probe auf `false` nicht. Beide Zustaende werden geprueft —
 * ein Test nur auf `true` wuerde `1` durchgehen lassen, sobald jemand den Cast entfernt
 * und die Vergleiche lockert.
 */
it('delivers all four meetup switches as real booleans', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $meetup = Meetup::factory()->create([
        'created_by' => $user->id,
        'visible_on_map' => true,
        'is_active' => true,
        'rsvp_enabled' => true,
        'attendees_public' => true,
    ]);

    $this->getJson('/api/my-meetups/'.$meetup->id)
        ->assertSuccessful()
        ->assertJsonPath('data.visible_on_map', true)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.rsvp_enabled', true)
        ->assertJsonPath('data.attendees_public', true);
});

it('delivers the false side as false, not 0', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $meetup = Meetup::factory()->create([
        'created_by' => $user->id,
        'visible_on_map' => false,
        'is_active' => false,
        'rsvp_enabled' => false,
        'attendees_public' => false,
    ]);

    $this->getJson('/api/my-meetups/'.$meetup->id)
        ->assertSuccessful()
        ->assertJsonPath('data.visible_on_map', false)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.rsvp_enabled', false)
        ->assertJsonPath('data.attendees_public', false);
});

it('reads visible_on_map back off the model as a boolean', function () {
    $meetup = Meetup::factory()->create(['visible_on_map' => 1]);

    expect($meetup->fresh()->visible_on_map)->toBeTrue()
        ->and(Meetup::factory()->create(['visible_on_map' => 0])->fresh()->visible_on_map)->toBeFalse();
});
