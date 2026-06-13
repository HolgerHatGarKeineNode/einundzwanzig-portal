<?php

use App\Models\City;
use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a guest', function () {
    $this->postJson('/api/meetup', [
        'name' => 'Einundzwanzig Ansbach',
        'city_id' => City::factory()->create()->id,
    ])->assertUnauthorized();
});

it('lets an authenticated user create', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $this->postJson('/api/meetup', [
        'name' => 'Einundzwanzig Ansbach',
        'city_id' => City::factory()->create()->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Einundzwanzig Ansbach');

    $this->assertDatabaseHas('meetups', [
        'name' => 'Einundzwanzig Ansbach',
        'created_by' => $user->id,
    ]);
});

it('fails validation', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/meetup', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'city_id']);
});

it('lets the owner update', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'name' => 'Plan B Lugano',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Plan B Lugano');
});

it('forbids updating someone elses', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'name' => 'Plan B Lugano',
    ])->assertForbidden();
});

it('forbids updating as a pivot member who is not the creator', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs($member = User::factory()->create());
    $meetup->users()->attach($member);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'name' => 'Plan B Lugano',
    ])->assertForbidden();
});

it('returns the dashboard-selected meetups in mine index', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $selected = Meetup::factory()->count(2)->create();
    $unselected = Meetup::factory()->create();

    $user->meetups()->attach($selected);

    $response = $this->getJson('/api/my-meetups');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)
        ->toContain(...$selected->pluck('id')->all())
        ->not->toContain($unselected->id);

    collect($response->json('data'))->each(
        fn ($meetup) => expect($meetup)->toHaveKey('logo')
            ->and($meetup['logo'])->toBeString()
    );
});

it('lets a pivot member view in mine show', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create();
    $user->meetups()->attach($meetup);

    $this->getJson('/api/my-meetups/'.$meetup->id)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $meetup->id);
});

it('forbids viewing a meetup the user has not selected in mine show', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/my-meetups/'.$meetup->id)->assertForbidden();
});
