<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use swentel\nostr\Key\Key as NostrKey;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = User::factory()->create();
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
    ]);
});

/** Deterministischer, gültiger npub aus einem 64-stelligen Hex-Pubkey. */
function grantStewardNpub(string $hex): string
{
    return (new NostrKey)->convertPublicKeyToBech32($hex);
}

it('grants the steward role to an existing user', function () {
    $npub = grantStewardNpub(str_pad('a', 64, 'a'));
    $user = User::factory()->create(['nostr' => $npub]);

    $this->artisan('meetups:grant-steward', ['npub' => $npub])->assertSuccessful();

    expect($user->fresh()->managesAllMeetups())->toBeTrue()
        ->and($user->fresh()->can('manageLeaders', $this->meetup))->toBeTrue();
});

it('refuses an unknown npub instead of silently creating an account', function () {
    $npub = grantStewardNpub(str_pad('b', 64, 'b'));

    $this->artisan('meetups:grant-steward', ['npub' => $npub])->assertFailed();

    expect(User::query()->where('nostr', $npub)->exists())->toBeFalse();
});

it('creates the account when --create is given', function () {
    $npub = grantStewardNpub(str_pad('b', 64, 'b'));

    $this->artisan('meetups:grant-steward', ['npub' => $npub, '--create' => true])->assertSuccessful();

    $user = User::query()->where('nostr', $npub)->firstOrFail();

    expect($user->managesAllMeetups())->toBeTrue();
});

it('leaves created_by and the meetup_user pivot untouched', function () {
    $npub = grantStewardNpub(str_pad('c', 64, 'c'));
    $pivotsBefore = DB::table('meetup_user')->count();

    $this->artisan('meetups:grant-steward', ['npub' => $npub, '--create' => true])->assertSuccessful();

    $steward = User::query()->where('nostr', $npub)->firstOrFail();

    expect($this->meetup->fresh()->created_by)->toBe($this->creator->id)
        ->and(DB::table('meetup_user')->count())->toBe($pivotsBefore)
        ->and(Meetup::query()->associatedWith($steward->id)->count())->toBe(0);
});

it('revokes the role again', function () {
    $npub = grantStewardNpub(str_pad('d', 64, 'd'));

    $this->artisan('meetups:grant-steward', ['npub' => $npub, '--create' => true])->assertSuccessful();
    $this->artisan('meetups:grant-steward', ['npub' => $npub, '--revoke' => true])->assertSuccessful();

    $user = User::query()->where('nostr', $npub)->firstOrFail();

    expect($user->managesAllMeetups())->toBeFalse()
        ->and($user->can('manageLeaders', $this->meetup))->toBeFalse();
});

it('fails on an invalid npub without creating a user', function () {
    $this->artisan('meetups:grant-steward', ['npub' => 'npub-nope'])->assertFailed();

    expect(User::query()->where('nostr', 'npub-nope')->exists())->toBeFalse();
});

it('fails when revoking from an unknown npub', function () {
    $this->artisan('meetups:grant-steward', [
        'npub' => grantStewardNpub(str_pad('e', 64, 'e')),
        '--revoke' => true,
    ])->assertFailed();
});
