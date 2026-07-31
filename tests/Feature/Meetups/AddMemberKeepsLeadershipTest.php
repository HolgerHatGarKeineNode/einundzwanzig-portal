<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = User::factory()->create();
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
    ]);
});

it('does not demote a leader who adds the meetup to "my meetups" again', function () {
    $leader = User::factory()->create();
    $this->meetup->promoteLeader($leader);

    $wasAdded = $this->meetup->addMember($leader);

    expect($wasAdded)->toBeFalse()
        ->and($this->meetup->fresh()->isLeader($leader))->toBeTrue();
});

it('does not demote the meetup creator via addToMine on the API', function () {
    Sanctum::actingAs($this->creator);

    $this->postJson("/api/my-meetups/{$this->meetup->slug}")->assertSuccessful();

    expect($this->meetup->fresh()->isLeader($this->creator))->toBeTrue();
});

it('keeps a demoted leader able to create events after re-adding the meetup', function () {
    $leader = User::factory()->create();
    $this->meetup->promoteLeader($leader);
    $this->meetup->addMember($leader);

    Sanctum::actingAs($leader);

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $this->meetup->id,
        'start' => now()->addWeek()->toDateTimeString(),
        'location' => 'Lindenhof Keulos',
    ])->assertSuccessful();
});

it('still adds a new member as a non-leader', function () {
    $member = User::factory()->create();

    $wasAdded = $this->meetup->addMember($member);

    expect($wasAdded)->toBeTrue()
        ->and($this->meetup->fresh()->isLeader($member))->toBeFalse()
        ->and($this->meetup->fresh()->hasMember($member))->toBeTrue();
});
