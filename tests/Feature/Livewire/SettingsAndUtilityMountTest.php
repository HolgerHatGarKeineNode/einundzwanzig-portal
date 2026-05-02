<?php

use Livewire\Livewire;

it('mounts settings.profile when authenticated', function () {
    actingAsUser();
    Livewire::test('settings.profile')->assertStatus(200);
});

it('mounts settings.password when authenticated', function () {
    actingAsUser();
    Livewire::test('settings.password')->assertStatus(200);
});

it('mounts settings.appearance when authenticated', function () {
    actingAsUser();
    Livewire::test('settings.appearance')->assertStatus(200);
});

it('mounts settings.delete-user-form when authenticated', function () {
    actingAsUser();
    Livewire::test('settings.delete-user-form')->assertStatus(200);
});

it('mounts welcome', function () {
    Livewire::test('welcome')->assertStatus(200);
});

it('mounts language.selector', function () {
    Livewire::test('language.selector')->assertStatus(200);
});

it('mounts timezone.chooser', function () {
    Livewire::test('timezone.chooser')->assertStatus(200);
});

it('mounts dashboard.activities', function () {
    actingAsUser();
    Livewire::test('dashboard.activities')->assertStatus(200);
});

it('mounts dashboard.top-countries', function () {
    Livewire::test('dashboard.top-countries')->assertStatus(200);
});

it('mounts dashboard.top-meetups', function () {
    Livewire::test('dashboard.top-meetups')->assertStatus(200);
});
