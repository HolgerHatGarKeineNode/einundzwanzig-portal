<?php

use App\Models\LoginKey;
use App\Models\User;
use Livewire\Livewire;

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

it('completes a Lightning login and redirects to the dashboard when a recent LoginKey exists', function () {
    $user = User::factory()->create();
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create([
        'user_id' => $user->id,
        'k1' => $k1,
        'created_at' => now(),
    ]);

    $response = $this->withSession(['lang_country' => 'de-DE', 'locale' => 'de'])
        ->get(route('auth.ln.complete', ['k1' => $k1]));

    $response->assertRedirect(route('dashboard', ['country' => 'de']));
    $this->assertAuthenticatedAs($user);
});

it('redirects to login when the LoginKey is older than 5 minutes', function () {
    $user = User::factory()->create();
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create([
        'user_id' => $user->id,
        'k1' => $k1,
        'created_at' => now()->subMinutes(10),
    ]);

    $this->get(route('auth.ln.complete', ['k1' => $k1]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('redirects to login when no LoginKey exists for the k1', function () {
    $k1 = bin2hex(random_bytes(32));

    $this->get(route('auth.ln.complete', ['k1' => $k1]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('returns 404 when the k1 path parameter is malformed', function () {
    $this->get('/auth/complete-lightning/not-hex-string-not-64-chars')
        ->assertNotFound();
});

it('dispatches lightning-login-ready from auth.login checkAuth() without rotating the session', function () {
    $user = User::factory()->create();
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create([
        'user_id' => $user->id,
        'k1' => $k1,
        'created_at' => now(),
    ]);

    Livewire::test('auth.login')
        ->set('k1', $k1)
        ->call('checkAuth')
        ->assertDispatched('lightning-login-ready', url: route('auth.ln.complete', ['k1' => $k1]));

    // The poll handler must NOT log the user in directly — that's the
    // controller's job. Logging in here would rotate the session id and
    // CSRF token mid-poll, producing 419s on any in-flight Livewire request.
    // It also must NOT return a server-side redirect: emitting an event lets
    // Alpine pause wire:poll via lightningLoginInProgress before navigating,
    // which avoids the "request loop without redirect" symptom in production.
    expect(auth()->check())->toBeFalse();
});

it('does not dispatch lightning-login-ready when no LoginKey exists', function () {
    $k1 = bin2hex(random_bytes(32));

    Livewire::test('auth.login')
        ->set('k1', $k1)
        ->call('checkAuth')
        ->assertNotDispatched('lightning-login-ready');

    expect(auth()->check())->toBeFalse();
});

it('does not dispatch lightning-login-ready when the LoginKey is older than 5 minutes', function () {
    $user = User::factory()->create();
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create([
        'user_id' => $user->id,
        'k1' => $k1,
        'created_at' => now()->subMinutes(10),
    ]);

    Livewire::test('auth.login')
        ->set('k1', $k1)
        ->call('checkAuth')
        ->assertNotDispatched('lightning-login-ready');

    expect(auth()->check())->toBeFalse();
});
