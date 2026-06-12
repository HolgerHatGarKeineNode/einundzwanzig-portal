<?php

use App\Jobs\FetchNostrProfileJob;
use App\Models\LoginKey;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use swentel\nostr\Event\Event as NostrEvent;
use swentel\nostr\Key\Key as NostrKey;
use swentel\nostr\Sign\Sign as NostrSign;

/**
 * Build a NIP-55-style signed login event for a given k1 challenge, as an
 * Android signer (e.g. Amber) would return it to the callback URL.
 *
 * @return array{0: array<string, mixed>, 1: string}
 */
function makeSignedMobileNostrEvent(string $challenge): array
{
    $keyGen = new NostrKey;
    $privateKey = $keyGen->generatePrivateKey();
    $publicKey = $keyGen->getPublicKey($privateKey);

    $event = new NostrEvent;
    $event->setKind(22242)
        ->setCreatedAt(time())
        ->setContent('')
        ->setTags([['challenge', $challenge]]);

    (new NostrSign)->signEvent($event, $privateKey);

    $signed = [
        'id' => $event->getId(),
        'pubkey' => $event->getPublicKey(),
        'created_at' => $event->getCreatedAt(),
        'kind' => $event->getKind(),
        'tags' => $event->getTags(),
        'content' => $event->getContent(),
        'sig' => $event->getSignature(),
    ];

    return [$signed, $keyGen->convertPublicKeyToBech32($publicKey)];
}

it('renders the mobile login page for guests and stores the flow state in the session', function () {
    $response = $this->get('/auth/mobile?redirect_uri=einundzwanzig%3A%2F%2Fauth&device_name=Pixel%2010');

    $response->assertOk();

    expect(session('mobile_auth.redirect_uri'))->toBe('einundzwanzig://auth')
        ->and(session('mobile_auth.device_name'))->toBe('Pixel 10');
});

it('rejects redirect uris that are not whitelisted', function () {
    $this->get('/auth/mobile?redirect_uri=https%3A%2F%2Fevil.example%2Fphish')
        ->assertForbidden();
});

it('stores a login key and creates a user when the nostr signer callback verifies', function () {
    Queue::fake();

    $k1 = bin2hex(random_bytes(32));
    [$signedEvent, $npub] = makeSignedMobileNostrEvent($k1);

    $response = $this->get('/api/nostr-login-callback?k1='.$k1.'&event='.urlencode(json_encode($signedEvent)));

    $response->assertOk()->assertJson(['status' => 'OK']);

    $user = User::query()->where('nostr', $npub)->first();
    expect($user)->not->toBeNull();

    $loginKey = LoginKey::query()->where('k1', $k1)->first();
    expect($loginKey)->not->toBeNull()
        ->and($loginKey->user_id)->toBe($user->id);

    Queue::assertPushed(FetchNostrProfileJob::class);
});

it('rejects a nostr signer callback whose event is bound to a different challenge', function () {
    $k1 = bin2hex(random_bytes(32));
    [$signedEvent] = makeSignedMobileNostrEvent(bin2hex(random_bytes(32)));

    $response = $this->get('/api/nostr-login-callback?k1='.$k1.'&event='.urlencode(json_encode($signedEvent)));

    $response->assertBadRequest();
    expect(LoginKey::query()->where('k1', $k1)->exists())->toBeFalse();
});

it('completes the login via the path-based signer callback and renders the app handoff', function () {
    Queue::fake();

    $k1 = bin2hex(random_bytes(32));
    [$signedEvent, $npub] = makeSignedMobileNostrEvent($k1);

    // Amber appends the URL-encoded signed event after the trailing slash
    // and drops any query string from the callback URL.
    $response = $this
        ->withSession(['mobile_auth' => ['redirect_uri' => 'einundzwanzig://auth', 'device_name' => 'Pixel 10']])
        ->get('/auth/mobile/signed/'.$k1.'/'.rawurlencode(json_encode($signedEvent)));

    // The signed callback renders the handoff page directly (no 302) so the
    // user can tap the einundzwanzig:// deep-link button to return to the app.
    $response->assertOk()->assertSee('einundzwanzig://auth?token=', false);

    $user = User::query()->where('nostr', $npub)->first();
    expect($user)->not->toBeNull();

    $token = $user->tokens()->first();
    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('Pixel 10');

    expect(LoginKey::query()->where('k1', $k1)->exists())->toBeTrue();
    Queue::assertPushed(FetchNostrProfileJob::class);

    // The deep-link token must authenticate API requests.
    $plainTextToken = urldecode(str($response->getContent())->after('einundzwanzig://auth?token=')->before('"')->value());
    $this->getJson('/api/user', ['Authorization' => 'Bearer '.$plainTextToken])
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('exchanges a signed event for a token via the mobile token endpoint', function () {
    Queue::fake();

    $k1 = bin2hex(random_bytes(32));
    [$signedEvent, $npub] = makeSignedMobileNostrEvent($k1);

    $response = $this->postJson('/api/mobile/token', [
        'k1' => $k1,
        'event' => $signedEvent,
        'device_name' => 'Pixel 10',
    ]);

    $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name']]);

    $user = User::query()->where('nostr', $npub)->first();
    expect($user)->not->toBeNull()
        ->and($user->tokens()->first()->name)->toBe('Pixel 10');

    $this->getJson('/api/user', ['Authorization' => 'Bearer '.$response->json('token')])
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('rejects a token exchange with a mismatched challenge', function () {
    $k1 = bin2hex(random_bytes(32));
    [$signedEvent] = makeSignedMobileNostrEvent(bin2hex(random_bytes(32)));

    $this->postJson('/api/mobile/token', [
        'k1' => $k1,
        'event' => $signedEvent,
    ])->assertBadRequest();
});

it('rejects a path-based signer callback with a tampered challenge', function () {
    $k1 = bin2hex(random_bytes(32));
    [$signedEvent] = makeSignedMobileNostrEvent(bin2hex(random_bytes(32)));

    $this->get('/auth/mobile/signed/'.$k1.'/'.rawurlencode(json_encode($signedEvent)))
        ->assertRedirect(route('auth.mobile'));

    expect(LoginKey::query()->where('k1', $k1)->exists())->toBeFalse();
});

it('issues a token and renders the handoff when completing a verified login', function () {
    $user = User::factory()->create();
    $k1 = bin2hex(random_bytes(32));
    LoginKey::query()->create(['k1' => $k1, 'user_id' => $user->id]);

    $response = $this
        ->withSession(['mobile_auth' => ['redirect_uri' => 'einundzwanzig://auth', 'device_name' => 'Pixel 10']])
        ->get('/auth/mobile/complete/'.$k1);

    $response->assertOk()->assertSee('einundzwanzig://auth?token=', false);

    $token = $user->tokens()->first();
    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('Pixel 10');

    // The plain-text token handed to the app must authenticate API requests.
    $plainTextToken = urldecode(str($response->getContent())->after('einundzwanzig://auth?token=')->before('"')->value());
    $this->getJson('/api/user', ['Authorization' => 'Bearer '.$plainTextToken])
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('redirects back to the mobile login when the k1 is unknown or expired', function () {
    $this->get('/auth/mobile/complete/'.bin2hex(random_bytes(32)))
        ->assertRedirect(route('auth.mobile'));
});

it('lets an already authenticated user connect the app and replaces tokens of the same device', function () {
    $user = User::factory()->create();
    $user->createToken('Pixel 10');

    $response = $this
        ->actingAs($user)
        ->withSession(['mobile_auth' => ['redirect_uri' => 'einundzwanzig://auth', 'device_name' => 'Pixel 10']])
        ->post('/auth/mobile/confirm');

    $response->assertOk()->assertSee('einundzwanzig://auth?token=', false);
    expect($user->tokens()->where('name', 'Pixel 10')->count())->toBe(1);
});

it('shows the confirmation screen instead of the login methods for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/auth/mobile')
        ->assertOk()
        ->assertSee(route('auth.mobile.confirm'));
});

it('returns the token owner profile on /api/user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', $user->name);
});

it('denies /api/user without a token', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('revokes the requesting token on mobile logout', function () {
    $user = User::factory()->create();
    $plainTextToken = $user->createToken('Pixel 10')->plainTextToken;

    $this->deleteJson('/api/mobile/token', [], ['Authorization' => 'Bearer '.$plainTextToken])
        ->assertOk()
        ->assertJson(['status' => 'OK']);

    expect($user->tokens()->count())->toBe(0);

    // The revoked token no longer authenticates API requests. The guard
    // caches the resolved user within a test, so reset it first.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/user', ['Authorization' => 'Bearer '.$plainTextToken])
        ->assertUnauthorized();
});

it('only revokes the token used for the logout request', function () {
    $user = User::factory()->create();
    $phoneToken = $user->createToken('Pixel 10')->plainTextToken;
    $user->createToken('Tablet');

    $this->deleteJson('/api/mobile/token', [], ['Authorization' => 'Bearer '.$phoneToken])
        ->assertOk();

    expect($user->tokens()->pluck('name')->all())->toBe(['Tablet']);
});

it('denies the mobile logout without a token', function () {
    $this->deleteJson('/api/mobile/token')->assertUnauthorized();
});
