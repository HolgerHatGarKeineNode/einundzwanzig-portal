<?php

use App\Models\User;
use Livewire\Livewire;

it('mounts the api tokens page when authenticated', function () {
    actingAsUser();

    Livewire::test('settings.api-tokens')->assertStatus(200);
});

it('creates a personal access token and reveals it once', function () {
    $user = actingAsUser();

    Livewire::test('settings.api-tokens')
        ->set('name', 'Externer Kurs-Sync')
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertDispatched('token-created')
        ->assertSet('name', '')
        ->assertSet('plainTextToken', fn ($token) => is_string($token) && str_contains($token, '|'));

    expect($user->tokens()->where('name', 'Externer Kurs-Sync')->exists())->toBeTrue();
});

it('requires a token name', function () {
    actingAsUser();

    Livewire::test('settings.api-tokens')
        ->set('name', '')
        ->call('createToken')
        ->assertHasErrors(['name' => 'required']);
});

it('revokes a token', function () {
    $user = actingAsUser();
    $token = $user->createToken('to-be-revoked')->accessToken;

    Livewire::test('settings.api-tokens')
        ->call('deleteToken', $token->id)
        ->assertDispatched('token-deleted');

    expect($user->tokens()->whereKey($token->id)->exists())->toBeFalse();
});

it('only lists the authenticated user\'s own tokens', function () {
    $user = actingAsUser();
    $user->createToken('mine');

    $other = User::factory()->create();
    $other->createToken('theirs');

    Livewire::test('settings.api-tokens')
        ->assertViewHas('tokens', fn ($tokens) => $tokens->count() === 1 && $tokens->first()->name === 'mine');
});

it('cannot revoke a token belonging to another user', function () {
    actingAsUser();
    $other = User::factory()->create();
    $foreignToken = $other->createToken('theirs')->accessToken;

    Livewire::test('settings.api-tokens')
        ->call('deleteToken', $foreignToken->id);

    expect($other->tokens()->whereKey($foreignToken->id)->exists())->toBeTrue();
});
