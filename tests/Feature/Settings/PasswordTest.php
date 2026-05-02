<?php

use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('updates the password when current password is correct', function () {
    $user = actingAsUser(['password' => Hash::make('old-password')]);

    Livewire::test('settings.password')
        ->set('current_password', 'old-password')
        ->set('password', 'new-strong-password!')
        ->set('password_confirmation', 'new-strong-password!')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertDispatched('password-updated');

    expect(Hash::check('new-strong-password!', $user->refresh()->password))->toBeTrue();
});

it('rejects an incorrect current password', function () {
    actingAsUser(['password' => Hash::make('correct-password')]);

    Livewire::test('settings.password')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-strong-password!')
        ->set('password_confirmation', 'new-strong-password!')
        ->call('updatePassword')
        ->assertHasErrors(['current_password' => 'current_password']);
});

it('rejects mismatched password confirmation', function () {
    actingAsUser(['password' => Hash::make('correct-password')]);

    Livewire::test('settings.password')
        ->set('current_password', 'correct-password')
        ->set('password', 'new-strong-password!')
        ->set('password_confirmation', 'different-confirmation')
        ->call('updatePassword')
        ->assertHasErrors(['password' => 'confirmed']);
});
