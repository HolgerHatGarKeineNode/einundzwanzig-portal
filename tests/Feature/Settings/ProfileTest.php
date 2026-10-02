<?php

use App\Models\Country;
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

it('shows the npub of a nostr account so it can be shared', function () {
    $user = User::factory()->withNostr()->create();
    $this->actingAs($user);

    Livewire::test('settings.profile')
        ->assertSee($user->nostr)
        ->assertDontSee(__('Nostr-Schlüssel verbinden'));
});

it('offers to connect a nostr key when the account has none', function () {
    actingAsUser(['nostr' => null]);

    Livewire::test('settings.profile')
        ->assertSee(__('Nostr-Schlüssel verbinden'))
        ->assertDontSee('npub1');
});

it('shows the shortened npub and a copy action in the user menu', function () {
    Country::factory()->create(['code' => 'de']);
    $npub = 'npub1'.str_repeat('q', 54).'wxyz';
    actingAsUser(['nostr' => $npub]);

    $this->get('/de/settings/profile')
        ->assertOk()
        ->assertSee('npub1qqqq…wxyz')
        ->assertSee('data-testid="user-menu-copy-npub"', false)
        ->assertSee("x-copy-to-clipboard=\"'{$npub}'\"", false);
});

it('shows no npub in the user menu of an account without one', function (?string $nostr) {
    Country::factory()->create(['code' => 'de']);
    actingAsUser(['nostr' => $nostr]);

    $this->get('/de/settings/profile')
        ->assertOk()
        ->assertDontSee('data-testid="user-menu-npub"', false)
        ->assertDontSee('data-testid="user-menu-copy-npub"', false);
})->with(['lightning only' => null, 'not an npub' => 'deadbeef']);
