<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Original Name']);
});

it('updates an existing Meetup name when authenticated', function () {
    actingAsUser();

    Livewire::test('meetups.edit', ['meetup' => $this->meetup])
        ->set('name', 'Updated Name')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($this->meetup->refresh()->name)->toBe('Updated Name');
});

it('rejects update when name collides with another existing Meetup', function () {
    Meetup::factory()->create(['name' => 'Other Name', 'city_id' => $this->city->id]);
    actingAsUser();

    Livewire::test('meetups.edit', ['meetup' => $this->meetup])
        ->set('name', 'Other Name')
        ->call('updateMeetup')
        ->assertHasErrors(['name' => 'unique']);
});

it('allows update when name is unchanged (Rule::unique ignores own id)', function () {
    actingAsUser();

    Livewire::test('meetups.edit', ['meetup' => $this->meetup])
        ->set('name', 'Original Name')
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();
});

it('redirects guests when accessing meetup-edit', function () {
    $this->get('/de/meetup-edit/'.$this->meetup->id)->assertRedirect(route('login'));
});
