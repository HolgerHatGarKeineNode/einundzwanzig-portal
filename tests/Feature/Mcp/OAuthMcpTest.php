<?php

use App\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

it('configures the passport-backed api guard', function () {
    expect(config('auth.guards.api.driver'))->toBe('passport');
});

it('exposes protected-resource discovery metadata', function () {
    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonStructure(['resource', 'authorization_servers', 'scopes_supported'])
        ->assertJsonPath('scopes_supported', ['mcp:use']);
});

it('exposes authorization-server discovery metadata pointing at passport', function () {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('token_endpoint', route('passport.token'))
        ->assertJsonPath('scopes_supported', ['mcp:use'])
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token']);
});

it('returns 401 with an OAuth discovery WWW-Authenticate header for guests', function () {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('resource_metadata=')
        ->toContain('oauth-protected-resource');
});

it('lets a permitted client register dynamically', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])
        ->assertOk()
        ->assertJsonStructure(['client_id', 'redirect_uris', 'scope'])
        ->assertJsonPath('scope', 'mcp:use');
});

it('rejects dynamic registration from a disallowed redirect domain', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Evil',
        'redirect_uris' => ['https://evil.example/callback'],
    ])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');
});

it('authenticates the mcp endpoint via a passport oauth token', function () {
    Passport::actingAs(User::factory()->create(), ['mcp:use']);

    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'ping',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('WWW-Authenticate'))->toBeNull();
});

it('still authenticates the mcp endpoint via a static sanctum token', function () {
    $token = User::factory()->create()->createToken('claude-code')->plainTextToken;

    $response = $this->withToken($token)->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'ping',
    ]);

    $response->assertSuccessful();
});

it('rejects a passport token that lacks the mcp:use scope', function () {
    Passport::actingAs(User::factory()->create(), []);

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'ping',
    ])->assertForbidden();
});

it('rejects an authorize request that uses plain PKCE instead of S256', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/oauth/authorize?response_type=code&client_id=1&redirect_uri=https%3A%2F%2Fclaude.ai%2Fcb&code_challenge=abc123&code_challenge_method=plain')
        ->assertStatus(400);
});

it('redirects a guest from the authorize endpoint to login and stores the intended url', function () {
    $clients = app(ClientRepository::class);
    $client = $clients->createAuthorizationCodeGrantClient(
        name: 'Claude',
        redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
        confidential: false,
    );

    $response = $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect(route('login'));
    expect(session('url.intended'))->toContain('/oauth/authorize');
});
