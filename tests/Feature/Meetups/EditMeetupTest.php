<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('updates an existing Meetup name when authenticated', function () {
    $owner = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $owner->id,
    ]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Updated Name')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->refresh()->name)->toBe('Updated Name');
});

it('rejects update when name collides with another existing Meetup', function () {
    $owner = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $owner->id,
    ]);
    Meetup::factory()->create(['name' => 'Other Name', 'city_id' => $this->city->id]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Other Name')
        ->call('updateMeetup')
        ->assertHasErrors(['name' => 'unique']);
});

it('allows update when name is unchanged (Rule::unique ignores own id)', function () {
    $owner = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $owner->id,
    ]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Original Name')
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();
});

it('redirects guests when accessing meetup-edit', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);

    $this->get('/de/meetup-edit/'.$meetup->id)->assertRedirect(route('login'));
});
