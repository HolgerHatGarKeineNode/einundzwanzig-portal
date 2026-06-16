<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('promotes all existing members to leaders', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $member = User::factory()->create();
    $meetup->addMember($member); // is_leader = false

    expect($meetup->isLeader($member))->toBeFalse();

    $this->artisan('meetups:promote-existing-leaders')->assertSuccessful();

    expect($meetup->fresh()->isLeader($member))->toBeTrue();
});

it('ensures the creator is a leader even for legacy meetups without a pivot row', function () {
    $creator = User::factory()->create();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $creator->id]);
    // Alt-Meetup simulieren: Ersteller-Pivot entfernen.
    $meetup->users()->detach();
    expect($meetup->hasMember($creator))->toBeFalse();

    $this->artisan('meetups:promote-existing-leaders')->assertSuccessful();

    expect($meetup->fresh()->isLeader($creator))->toBeTrue();
});

it('does not write on a dry run', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $member = User::factory()->create();
    $meetup->addMember($member);

    $this->artisan('meetups:promote-existing-leaders --dry-run')->assertSuccessful();

    expect(DB::table('meetup_user')->where('user_id', $member->id)->value('is_leader'))->toBe(0);
});
