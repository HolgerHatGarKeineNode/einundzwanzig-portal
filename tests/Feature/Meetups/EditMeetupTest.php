<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('updates an existing Meetup name as the creator', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Updated Name')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->refresh()->name)->toBe('Updated Name');
});

it('rejects update when name collides with another existing Meetup', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);
    Meetup::factory()->create(['name' => 'Other Name', 'city_id' => $this->city->id]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Other Name')
        ->call('updateMeetup')
        ->assertHasErrors(['name' => 'unique']);
});

it('allows update when name is unchanged (Rule::unique ignores own id)', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Original Name')
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();
});

it('blocks updateMeetup when the user is not the creator', function () {
    actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => User::factory()->create()->id,
    ]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertStatus(403);

    expect($meetup->refresh()->name)->toBe('Original Name');
});

it('redirects guests when accessing meetup-edit', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);

    $this->get('/de/meetup-edit/'.$meetup->id)->assertRedirect(route('login'));
});
