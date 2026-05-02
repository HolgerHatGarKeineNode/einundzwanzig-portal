<?php

use App\Models\LoginKey;
use App\Models\User;

it('returns invalid request parameters when k1 is missing', function () {
    $this->get('/api/lnurl-auth-callback')
        ->assertStatus(400)
        ->assertJson([
            'status' => 'ERROR',
            'reason' => 'Invalid request parameters',
        ]);
});

it('returns invalid request parameters when k1 is the wrong length', function () {
    $this->getJson('/api/lnurl-auth-callback?'.http_build_query([
        'k1' => 'tooshort',
        'sig' => str_repeat('a', 128),
        'key' => str_repeat('a', 64),
    ]))
        ->assertStatus(400)
        ->assertJson(['status' => 'ERROR']);
});

it('returns invalid request parameters when k1 is not hex', function () {
    $this->getJson('/api/lnurl-auth-callback?'.http_build_query([
        'k1' => str_repeat('Z', 64),
        'sig' => str_repeat('a', 128),
        'key' => str_repeat('a', 64),
    ]))
        ->assertStatus(400)
        ->assertJson(['status' => 'ERROR']);
});

it('returns no error from /api/check-auth-error when k1 is missing', function () {
    $this->postJson('/api/check-auth-error', [])
        ->assertSuccessful()
        ->assertJson(['error' => null]);
});

it('returns no error from /api/check-auth-error when a recent LoginKey exists', function () {
    $user = User::factory()->create();
    $loginKey = LoginKey::factory()->create([
        'user_id' => $user->id,
        'created_at' => now(),
    ]);

    $this->postJson('/api/check-auth-error', ['k1' => $loginKey->k1])
        ->assertSuccessful()
        ->assertJson(['error' => null]);
});

it('returns a session-expired error when no LoginKey exists and elapsed_seconds exceeds 300', function () {
    $this->postJson('/api/check-auth-error', [
        'k1' => str_repeat('a', 64),
        'elapsed_seconds' => 400,
    ])
        ->assertSuccessful()
        ->assertJson(['error' => 'Session expired. Please try again.']);
});
