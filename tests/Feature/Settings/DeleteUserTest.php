<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('deletes the user and logs them out when password is correct', function () {
    $user = actingAsUser(['password' => Hash::make('correct-password')]);

    Livewire::test('settings.delete-user-form')
        ->set('password', 'correct-password')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(User::query()->find($user->id))->toBeNull();
    expect(auth()->check())->toBeFalse();
});

it('does not delete the user with an incorrect password', function () {
    $user = actingAsUser(['password' => Hash::make('correct-password')]);

    Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser')
        ->assertHasErrors(['password' => 'current_password']);

    expect(User::query()->find($user->id))->not->toBeNull();
});
