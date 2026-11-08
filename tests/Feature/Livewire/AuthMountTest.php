<?php

use Livewire\Livewire;

it('mounts the auth.login component', function () {
    Livewire::test('auth.login')->assertStatus(200);
});

it('mounts the auth.verify-email component', function () {
    actingAsUser();
    Livewire::test('auth.verify-email')->assertStatus(200);
});
