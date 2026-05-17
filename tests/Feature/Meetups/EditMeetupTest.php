<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('updates an existing Meetup name when the user has it in My-Meetups', function () {
    $member = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
    ]);
    $meetup->users()->attach($member);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Updated Name')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->refresh()->name)->toBe('Updated Name');
});

it('rejects update when name collides with another existing Meetup', function () {
    $member = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
    ]);
    $meetup->users()->attach($member);
    Meetup::factory()->create(['name' => 'Other Name', 'city_id' => $this->city->id]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Other Name')
        ->call('updateMeetup')
        ->assertHasErrors(['name' => 'unique']);
});

it('allows update when name is unchanged (Rule::unique ignores own id)', function () {
    $member = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
    ]);
    $meetup->users()->attach($member);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Original Name')
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();
});

it('blocks updateMeetup when the user has not added the meetup to My-Meetups', function () {
    actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
    ]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertStatus(403);

    expect($meetup->refresh()->name)->toBe('Original Name');
});

it('redirects guests when accessing meetup-edit', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);

    $this->get('/de/meetup-edit/'.$meetup->id)->assertRedirect(route('login'));
});
