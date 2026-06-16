<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use swentel\nostr\Key\Key as NostrKey;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = User::factory()->create();
    // Der created-Hook trägt den Ersteller automatisch als Leader ein.
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
    ]);
});

/** Deterministischer, gültiger npub aus einem 64-stelligen Hex-Pubkey. */
function npubFromHex(string $hex): string
{
    return (new NostrKey)->convertPublicKeyToBech32($hex);
}

it('rejects a guest listing leaders', function () {
    $this->getJson("/api/meetup/{$this->meetup->id}/leaders")->assertUnauthorized();
});

it('lists the creator as a protected leader', function () {
    Sanctum::actingAs($this->creator);

    $this->getJson("/api/meetup/{$this->meetup->id}/leaders")
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $this->creator->id)
        ->assertJsonPath('data.0.is_creator', true);
});

it('forbids a plain member from managing leaders', function () {
    $member = User::factory()->create();
    $this->meetup->addMember($member); // is_leader = false

    Sanctum::actingAs($member);

    $this->getJson("/api/meetup/{$this->meetup->id}/leaders")->assertForbidden();
    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => npubFromHex(str_pad('1', 64, '0'))])
        ->assertForbidden();
});

it('lets a leader appoint a new leader by npub, creating the account', function () {
    Sanctum::actingAs($this->creator);
    $npub = npubFromHex(str_pad('a', 64, 'a'));

    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => $npub])
        ->assertCreated();

    $newUser = User::where('nostr', $npub)->firstOrFail();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $newUser->id,
        'is_leader' => true,
    ]);
});

it('promotes an existing member instead of duplicating', function () {
    $npub = npubFromHex(str_pad('b', 64, 'b'));
    $existing = User::factory()->create(['nostr' => $npub]);
    $this->meetup->addMember($existing); // is_leader = false

    Sanctum::actingAs($this->creator);
    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => $npub])
        ->assertCreated();

    expect($this->meetup->users()->whereKey($existing->id)->count())->toBe(1);
    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $existing->id,
        'is_leader' => true,
    ]);
});

it('rejects an invalid npub', function () {
    Sanctum::actingAs($this->creator);

    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => 'not-an-npub'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['npub']);
});

it('lets a delegated leader appoint further leaders', function () {
    // Ersteller befördert ein Mitglied zum Leader …
    $delegate = User::factory()->create();
    $this->meetup->users()->syncWithoutDetaching([$delegate->id => ['is_leader' => true]]);

    // … der dann selbst weitere Leader einsetzen darf.
    Sanctum::actingAs($delegate);
    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => npubFromHex(str_pad('c', 64, 'c'))])
        ->assertCreated();
});

it('demotes a leader but keeps the membership', function () {
    $leader = User::factory()->create();
    $this->meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    Sanctum::actingAs($this->creator);
    $this->deleteJson("/api/meetup/{$this->meetup->id}/leaders/{$leader->id}")
        ->assertSuccessful();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $leader->id,
        'is_leader' => false,
    ]);
});

it('never lets the creator be demoted', function () {
    $leader = User::factory()->create();
    $this->meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    Sanctum::actingAs($leader);
    $this->deleteJson("/api/meetup/{$this->meetup->id}/leaders/{$this->creator->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $this->creator->id,
        'is_leader' => true,
    ]);
});

it('lets a delegated leader edit the meetup master data', function () {
    $leader = User::factory()->create();
    $this->meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    Sanctum::actingAs($leader);
    $this->patchJson("/api/meetup/{$this->meetup->id}", ['name' => 'Renamed by leader'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Renamed by leader');
});

it('forbids a plain member from editing the meetup master data', function () {
    $member = User::factory()->create();
    $this->meetup->addMember($member); // is_leader = false

    Sanctum::actingAs($member);
    $this->patchJson("/api/meetup/{$this->meetup->id}", ['name' => 'Hacked'])
        ->assertForbidden();
});

it('exposes is_leader on the my-meetups listing', function () {
    Sanctum::actingAs($this->creator);

    $this->getJson('/api/my-meetups')
        ->assertSuccessful()
        ->assertJsonPath('data.0.is_leader', true);
});
