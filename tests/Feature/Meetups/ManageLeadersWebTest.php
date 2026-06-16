<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Livewire\Livewire;
use swentel\nostr\Key\Key as NostrKey;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = actingAsUser();
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
    ]);
});

function webNpub(string $hex): string
{
    return (new NostrKey)->convertPublicKeyToBech32($hex);
}

it('appoints a leader by npub from the edit page', function () {
    $npub = webNpub(str_pad('a', 64, 'a'));

    Livewire::test('meetups.edit', ['meetup' => $this->meetup])
        ->set('leaderNpub', $npub)
        ->call('addLeader')
        ->assertHasNoErrors();

    $newUser = User::where('nostr', $npub)->firstOrFail();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $newUser->id,
        'is_leader' => true,
    ]);
});

it('rejects an invalid npub on the edit page', function () {
    Livewire::test('meetups.edit', ['meetup' => $this->meetup])
        ->set('leaderNpub', 'not-an-npub')
        ->call('addLeader')
        ->assertHasErrors('leaderNpub');
});

it('demotes a leader but keeps the membership', function () {
    $leader = User::factory()->create();
    $this->meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    Livewire::test('meetups.edit', ['meetup' => $this->meetup])
        ->call('removeLeader', $leader->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $leader->id,
        'is_leader' => false,
    ]);
});

it('never lets the creator be demoted', function () {
    Livewire::test('meetups.edit', ['meetup' => $this->meetup])
        ->call('removeLeader', $this->creator->id)
        ->assertStatus(403);

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $this->creator->id,
        'is_leader' => true,
    ]);
});

it('forbids a plain member from managing leaders via the edit page', function () {
    $member = User::factory()->create();
    $this->meetup->addMember($member); // is_leader = false

    $this->actingAs($member);

    // Nicht-Leader kommen schon nicht auf die Seite (authorizeAccess).
    Livewire::test('meetups.edit', ['meetup' => $this->meetup])
        ->assertStatus(403);
});
