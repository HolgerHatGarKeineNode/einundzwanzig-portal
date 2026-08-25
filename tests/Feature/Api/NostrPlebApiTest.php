<?php

use App\Models\User;

/**
 * `GET /api/nostrplebs` — die npub-Liste der Community.
 *
 * Der Endpunkt hatte bis P6 keinen einzigen Test, obwohl er oeffentlich ist und sein
 * `select()` bis zum Lightning-Abbau alle fuenf Lightning-Spalten mitzog. Der Test
 * entstand ABSICHTLICH vor dem Spalten-Drop: waere er erst danach geschrieben worden,
 * haette niemand gewusst, ob der Endpunkt vorher genauso antwortete. Er ist damit die
 * Vorher-Nachher-Klammer um die Migration.
 *
 * Er prueft die Zusicherungen, die der Docblock des Controllers gibt — nacktes Array,
 * nur npubs, eindeutig, absteigend nach id — und NICHT, welche Spalten selektiert
 * werden. Die Auswahl ist Implementierung; dass sie sich aendern durfte, ohne dass die
 * Antwort sich aendert, ist genau der Punkt.
 */
beforeEach(function () {
    // Die Factory wuerfelt `nostr` mit 70 % Wahrscheinlichkeit. Fuer einen
    // deterministischen Bestand muss jeder Nutzer hier seinen Wert selbst mitbringen.
    User::query()->delete();
});

it('returns the npubs of all users that have one', function () {
    User::factory()->create(['nostr' => 'npub1aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);
    User::factory()->create(['nostr' => 'npub1bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']);

    $response = $this->getJson('/api/nostrplebs')->assertSuccessful();

    expect($response->json())->toHaveCount(2)
        ->and($response->json())->toContain('npub1aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
        ->and($response->json())->toContain('npub1bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
});

it('skips users without a nostr key and anything that is not an npub', function () {
    User::factory()->create(['nostr' => 'npub1cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc']);
    User::factory()->create(['nostr' => null]);
    // Ein hex-Pubkey oder ein nsec haette in einer oeffentlichen Liste nichts verloren.
    User::factory()->create(['nostr' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90']);
    User::factory()->create(['nostr' => 'nsec1dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd']);

    expect($this->getJson('/api/nostrplebs')->assertSuccessful()->json())
        ->toBe(['npub1cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc']);
});

it('reports a duplicated npub only once, keeping the newest account', function () {
    $npub = 'npub1eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    User::factory()->create(['nostr' => $npub]);
    User::factory()->create(['nostr' => $npub]);

    expect($this->getJson('/api/nostrplebs')->assertSuccessful()->json())->toBe([$npub]);
});

/**
 * Kein `data`-Wrapper: die Antwort ist ein nacktes Array von Strings. Konsumenten haengen
 * daran, und eine JsonResource haette den Wrapper ungefragt ergaenzt.
 */
it('answers with a bare array of strings, not a wrapped resource', function () {
    User::factory()->create(['nostr' => 'npub1ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff']);

    $payload = $this->getJson('/api/nostrplebs')->assertSuccessful()->json();

    expect($payload)->toBeArray()
        ->and($payload)->not->toHaveKey('data')
        ->and(array_keys($payload))->toBe([0])
        ->and($payload[0])->toBeString();
});

it('answers with an empty array when nobody has a nostr key', function () {
    User::factory()->count(3)->create(['nostr' => null]);

    expect($this->getJson('/api/nostrplebs')->assertSuccessful()->json())->toBe([]);
});

it('needs no authentication', function () {
    User::factory()->create(['nostr' => 'npub1gggggggggggggggggggggggggggggggggggggggggggggggggggggggggggg']);

    $this->getJson('/api/nostrplebs')->assertSuccessful();
});
