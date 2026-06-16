<?php

use App\Models\Meetup;
use App\Models\User;
use Livewire\Livewire;

it('lets a leader open the event editor', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertOk();
});

it('blocks a non-leader member from the event editor', function () {
    $member = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => User::factory()->create()->id]);
    $meetup->addMember($member); // is_leader = false

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertStatus(403);
});

it('blocks a stranger from the event editor', function () {
    actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => User::factory()->create()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertStatus(403);
});
