<?php

use App\Models\User;
use Livewire\Livewire;

it('updates the profile name and email when authenticated', function () {
    $user = actingAsUser(['email' => 'old@example.com', 'name' => 'Old Name']);

    Livewire::test('settings.profile')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertDispatched('profile-updated', name: 'New Name');

    expect($user->refresh())
        ->name->toBe('New Name')
        ->email->toBe('new@example.com')
        ->email_verified_at->toBeNull();
});

it('rejects an empty name', function () {
    actingAsUser();

    Livewire::test('settings.profile')
        ->set('name', '')
        ->call('updateProfileInformation')
        ->assertHasErrors(['name' => 'required']);
});

it('does NOT enforce the unique-email rule because the email column is CipherSweet-encrypted (Rule::unique scans plain values against the encrypted column and never matches)', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    actingAsUser();

    Livewire::test('settings.profile')
        ->set('email', 'taken@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();
});

it('keeps email_verified_at when email is unchanged', function () {
    $user = actingAsUser([
        'email' => 'same@example.com',
        'email_verified_at' => now(),
    ]);

    Livewire::test('settings.profile')
        ->set('name', 'Different Name')
        ->set('email', 'same@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});
