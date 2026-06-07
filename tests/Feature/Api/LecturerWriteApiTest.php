<?php

use App\Models\Lecturer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a guest', function () {
    $this->postJson('/api/lecturers', [
        'name' => 'Saifedean Ammous',
    ])->assertUnauthorized();
});

it('lets an authenticated user create', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $this->postJson('/api/lecturers', [
        'name' => 'Saifedean Ammous',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Saifedean Ammous');

    $this->assertDatabaseHas('lecturers', [
        'name' => 'Saifedean Ammous',
        'created_by' => $user->id,
    ]);
});

it('fails validation', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/lecturers', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('lets the owner update', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $lecturer = Lecturer::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/lecturers/'.$lecturer->id, [
        'name' => 'Knut Svanholm',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Knut Svanholm');
});

it('forbids updating someone elses', function () {
    $owner = User::factory()->create();
    $lecturer = Lecturer::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/lecturers/'.$lecturer->id, [
        'name' => 'Knut Svanholm',
    ])->assertForbidden();
});

it('returns only own in mine index', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $other = User::factory()->create();

    Lecturer::factory()->count(2)->create(['created_by' => $user->id]);
    Lecturer::factory()->create(['created_by' => $other->id]);

    $response = $this->getJson('/api/my-lecturers');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
    collect($response->json('data'))->each(
        fn ($lecturer) => expect($lecturer['created_by'])->toBe($user->id)
    );
});

it('forbids viewing someone elses in mine show', function () {
    $owner = User::factory()->create();
    $lecturer = Lecturer::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/my-lecturers/'.$lecturer->id)
        ->assertForbidden();
});
