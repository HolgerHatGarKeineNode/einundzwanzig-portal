<?php

use App\Models\LoginKey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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

it('surfaces a Nostr-migration notice when a retired Lightning credential is used', function () {
    $k1 = bin2hex(random_bytes(32));
    Cache::put('lnurl:retired:'.$k1, true, 300);

    $response = $this->postJson('/api/check-auth-error', ['k1' => $k1]);
    $response->assertSuccessful();

    expect($response->json('error'))->toContain('Nostr');

    // Single-use: the notice is consumed, so a second poll no longer errors.
    $this->postJson('/api/check-auth-error', ['k1' => $k1])
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

it('completes a Lightning login and redirects to the dashboard when the user already has Nostr', function () {
    $user = User::factory()->create(['nostr' => 'npub1already'.str_repeat('0', 20)]);
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create([
        'user_id' => $user->id,
        'k1' => $k1,
        'created_at' => now(),
    ]);

    $response = $this->withSession(['lang_country' => 'de-DE', 'locale' => 'de', 'lightning_login_k1' => $k1])
        ->get(route('auth.ln.complete', ['k1' => $k1]));

    $response->assertRedirect(route('dashboard', ['country' => 'de']));
    $this->assertAuthenticatedAs($user);
});

it('sends a Lightning user without a Nostr identity into the migration wizard', function () {
    $user = User::factory()->create(['nostr' => null]);
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create([
        'user_id' => $user->id,
        'k1' => $k1,
        'created_at' => now(),
    ]);

    $response = $this->withSession(['lang_country' => 'de-DE', 'locale' => 'de', 'lightning_login_k1' => $k1])
        ->get(route('auth.ln.complete', ['k1' => $k1]));

    $response->assertRedirect(route('settings.link-identity', ['country' => 'de']));
    $this->assertAuthenticatedAs($user);
});

it('resumes the intended OAuth url after a Lightning login instead of going to the dashboard', function () {
    $user = User::factory()->create();
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create([
        'user_id' => $user->id,
        'k1' => $k1,
        'created_at' => now(),
    ]);

    $intended = url('/oauth/authorize?client_id=1&response_type=code&scope=mcp:use');

    $response = $this->withSession([
        'lang_country' => 'de-DE',
        'locale' => 'de',
        'url.intended' => $intended,
        'lightning_login_k1' => $k1,
    ])->get(route('auth.ln.complete', ['k1' => $k1]));

    $response->assertRedirect($intended);
    $this->assertAuthenticatedAs($user);
});

it('rejects a completion whose k1 does not match the session (login CSRF / relay)', function () {
    $victimAttackerAccount = User::factory()->create(['nostr' => null]);
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create([
        'user_id' => $victimAttackerAccount->id,
        'k1' => $k1,
        'created_at' => now(),
    ]);

    // A different browser (no matching session k1) tries to complete the login.
    $this->withSession(['lang_country' => 'de-DE', 'lightning_login_k1' => 'someone-elses-k1'])
        ->get(route('auth.ln.complete', ['k1' => $k1]))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('burns the login key so the same k1 cannot be replayed', function () {
    $user = User::factory()->create(['nostr' => 'npub1x'.str_repeat('0', 20)]);
    $k1 = bin2hex(random_bytes(32));
    LoginKey::factory()->create(['user_id' => $user->id, 'k1' => $k1, 'created_at' => now()]);

    $this->withSession(['lang_country' => 'de-DE', 'locale' => 'de', 'lightning_login_k1' => $k1])
        ->get(route('auth.ln.complete', ['k1' => $k1]))
        ->assertRedirect(route('dashboard', ['country' => 'de']));

    // Burned: the LoginKey is gone, so the "no LoginKey -> redirect to login"
    // path now blocks any replay of the same k1.
    expect(LoginKey::where('k1', $k1)->exists())->toBeFalse();
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
