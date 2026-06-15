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

it('rejects a guest adding a meetup to mine', function () {
    $meetup = Meetup::factory()->create();

    $this->postJson('/api/my-meetups/'.$meetup->slug)->assertUnauthorized();
});

it('lets an authenticated user add an existing foreign meetup to mine by slug', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs($user = User::factory()->create());

    $this->postJson('/api/my-meetups/'.$meetup->slug)
        ->assertCreated()
        ->assertJsonPath('data.id', $meetup->id);

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $meetup->id,
        'user_id' => $user->id,
        'is_leader' => false,
    ]);
});

it('is idempotent and returns 200 when the meetup is already mine', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create();
    $user->meetups()->attach($meetup, ['is_leader' => false]);

    $this->postJson('/api/my-meetups/'.$meetup->slug)
        ->assertOk()
        ->assertJsonPath('data.id', $meetup->id);

    expect($user->meetups()->whereKey($meetup->id)->count())->toBe(1);
});

it('returns 404 for an unknown meetup slug', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/my-meetups/does-not-exist-9999')->assertNotFound();
});

it('rejects a guest removing a meetup from mine', function () {
    $meetup = Meetup::factory()->create();

    $this->deleteJson('/api/my-meetups/'.$meetup->slug)->assertUnauthorized();
});

it('lets an authenticated user remove a meetup from mine by slug', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create();
    $user->meetups()->attach($meetup, ['is_leader' => false]);

    $this->deleteJson('/api/my-meetups/'.$meetup->slug)
        ->assertOk()
        ->assertJsonPath('data.id', $meetup->id);

    // Nur die Pivot-Zuordnung wird gelöst — die Stammdaten bleiben erhalten.
    $this->assertDatabaseMissing('meetup_user', [
        'meetup_id' => $meetup->id,
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseHas('meetups', ['id' => $meetup->id]);
});

it('is idempotent and returns 200 when the meetup is not mine', function () {
    Sanctum::actingAs(User::factory()->create());
    $meetup = Meetup::factory()->create();

    $this->deleteJson('/api/my-meetups/'.$meetup->slug)
        ->assertOk()
        ->assertJsonPath('data.id', $meetup->id);
});

it('only removes the acting users membership, not other members', function () {
    $other = User::factory()->create();
    $meetup = Meetup::factory()->create();
    $meetup->users()->attach($other, ['is_leader' => false]);

    Sanctum::actingAs($user = User::factory()->create());
    $meetup->users()->attach($user, ['is_leader' => false]);

    $this->deleteJson('/api/my-meetups/'.$meetup->slug)->assertOk();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $meetup->id,
        'user_id' => $other->id,
    ]);
    $this->assertDatabaseMissing('meetup_user', [
        'meetup_id' => $meetup->id,
        'user_id' => $user->id,
    ]);
});

it('returns 404 when removing an unknown meetup slug', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson('/api/my-meetups/does-not-exist-9999')->assertNotFound();
});
