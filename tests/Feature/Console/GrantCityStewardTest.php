<?php

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use swentel\nostr\Key\Key as NostrKey;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->creator = User::factory()->create();
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'created_by' => $this->creator->id,
    ]);
});

/** Deterministischer, gültiger npub aus einem 64-stelligen Hex-Pubkey. */
function grantCityStewardNpub(string $hex): string
{
    return (new NostrKey)->convertPublicKeyToBech32($hex);
}

it('grants the steward role to an existing user', function () {
    $npub = grantCityStewardNpub(str_pad('a', 64, 'a'));
    $user = User::factory()->create(['nostr' => $npub]);

    $this->artisan('cities:grant-steward', ['npub' => $npub])->assertSuccessful();

    expect($user->fresh()->managesAllCities())->toBeTrue()
        ->and($user->fresh()->can('updateIdentity', $this->city))->toBeTrue();
});

it('refuses an unknown npub instead of silently creating an account', function () {
    $npub = grantCityStewardNpub(str_pad('b', 64, 'b'));

    $this->artisan('cities:grant-steward', ['npub' => $npub])->assertFailed();

    expect(User::query()->where('nostr', $npub)->exists())->toBeFalse();
});

it('creates the account when --create is given', function () {
    $npub = grantCityStewardNpub(str_pad('b', 64, 'b'));

    $this->artisan('cities:grant-steward', ['npub' => $npub, '--create' => true])->assertSuccessful();

    $user = User::query()->where('nostr', $npub)->firstOrFail();

    expect($user->managesAllCities())->toBeTrue();
});

it('leaves created_by of existing cities untouched', function () {
    $npub = grantCityStewardNpub(str_pad('c', 64, 'c'));

    $this->artisan('cities:grant-steward', ['npub' => $npub, '--create' => true])->assertSuccessful();

    expect($this->city->fresh()->created_by)->toBe($this->creator->id);
});

it('revokes the role again', function () {
    $npub = grantCityStewardNpub(str_pad('d', 64, 'd'));

    $this->artisan('cities:grant-steward', ['npub' => $npub, '--create' => true])->assertSuccessful();
    $this->artisan('cities:grant-steward', ['npub' => $npub, '--revoke' => true])->assertSuccessful();

    $user = User::query()->where('nostr', $npub)->firstOrFail();

    expect($user->managesAllCities())->toBeFalse()
        ->and($user->can('updateIdentity', $this->city))->toBeFalse();
});

it('fails on an invalid npub without creating a user', function () {
    $this->artisan('cities:grant-steward', ['npub' => 'npub-nope'])->assertFailed();

    expect(User::query()->where('nostr', 'npub-nope')->exists())->toBeFalse();
});

it('fails when revoking from an unknown npub', function () {
    $this->artisan('cities:grant-steward', [
        'npub' => grantCityStewardNpub(str_pad('e', 64, 'e')),
        '--revoke' => true,
    ])->assertFailed();
});
