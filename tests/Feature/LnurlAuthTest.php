<?php

use App\Models\LoginKey;
use App\Models\User;

beforeEach(function () {
    LoginKey::query()->delete();
    User::query()->delete();
});

test('lnurl auth callback validates required parameters', function () {
    $response = $this->get(route('auth.ln.callback'));

    $response->assertStatus(400)
        ->assertJson([
            'status' => 'ERROR',
            'reason' => 'Invalid request parameters',
        ]);
});

test('lnurl auth callback handles signature verification failures', function () {
    $k1 = str()->random(64);
    $sig = str()->random(128);
    $key = str()->random(64);

    $response = $this->get(route('auth.ln.callback').'?k1='.$k1.'&sig='.$sig.'&key='.$key);

    $response->assertStatus(400)
        ->assertJson([
            'status' => 'ERROR',
            'reason' => 'Authentication failed. Please try again.',
        ]);
});

test('check error returns null when login key exists', function () {
    $k1 = str()->random(64);

    LoginKey::factory()->create([
        'k1' => $k1,
        'created_at' => now(),
    ]);

    $response = $this->postJson(route('auth.check-error'), [
        'k1' => $k1,
        'elapsed_seconds' => 120,
    ]);

    $response->assertStatus(200)
        ->assertJson(['error' => null]);
});

test('check error returns null when k1 not expired', function () {
    $k1 = str()->random(64);

    $response = $this->postJson(route('auth.check-error'), [
        'k1' => $k1,
        'elapsed_seconds' => 120,
    ]);

    $response->assertStatus(200)
        ->assertJson(['error' => null]);
});

test('check error returns expired message when k1 is expired', function () {
    $k1 = str()->random(64);

    $response = $this->postJson(route('auth.check-error'), [
        'k1' => $k1,
        'elapsed_seconds' => 300,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'error' => 'Session expired. Please try again.',
        ]);
});

test('check error returns null when no k1 provided', function () {
    $response = $this->postJson(route('auth.check-error'));

    $response->assertStatus(200)
        ->assertJson(['error' => null]);
});

test('check error returns null when login key is too old', function () {
    $k1 = str()->random(64);

    LoginKey::factory()->create([
        'k1' => $k1,
        'created_at' => now()->subMinutes(10),
    ]);

    $response = $this->postJson(route('auth.check-error'), [
        'k1' => $k1,
        'elapsed_seconds' => 600,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'error' => 'Session expired. Please try again.',
        ]);
});

test('check error finds valid login key within 5 minutes', function () {
    $k1 = str()->random(64);

    LoginKey::factory()->create([
        'k1' => $k1,
        'created_at' => now()->subMinutes(3),
    ]);

    $response = $this->postJson(route('auth.check-error'), [
        'k1' => $k1,
        'elapsed_seconds' => 180,
    ]);

    $response->assertStatus(200)
        ->assertJson(['error' => null]);
});
