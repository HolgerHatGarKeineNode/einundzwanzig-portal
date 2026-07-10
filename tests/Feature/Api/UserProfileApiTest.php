<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('rejects a guest', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('reports is_leader false for a user leading no meetup', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/user')
        ->assertSuccessful()
        ->assertJsonPath('is_leader', false);
});

it('reports is_leader true once the user leads any meetup', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $meetup->promoteLeader($user);

    Sanctum::actingAs($user);

    $this->getJson('/api/user')
        ->assertSuccessful()
        ->assertJsonPath('is_leader', true);
});

it('sends a no-store cache header so no external client caches the role', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/user')->assertSuccessful();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});
