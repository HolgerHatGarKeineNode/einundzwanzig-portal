<?php

use Livewire\Livewire;

it('mounts the auth.login component', function () {
    Livewire::test('auth.login')->assertStatus(200);
});

it('mounts the auth.forgot-password component', function () {
    Livewire::test('auth.forgot-password')->assertStatus(200);
});

it('mounts the auth.reset-password component with a token', function () {
    Livewire::withQueryParams(['email' => 'foo@example.com'])
        ->test('auth.reset-password', ['token' => 'fake-reset-token'])
        ->assertStatus(200);
});

it('mounts the auth.confirm-password component', function () {
    actingAsUser();
    Livewire::test('auth.confirm-password')->assertStatus(200);
});

it('mounts the auth.verify-email component', function () {
    actingAsUser();
    Livewire::test('auth.verify-email')->assertStatus(200);
});
