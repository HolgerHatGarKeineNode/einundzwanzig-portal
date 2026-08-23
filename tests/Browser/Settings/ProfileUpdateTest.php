<?php

use App\Models\User;

it('lets an authenticated user update their profile name and persists it', function () {
    $user = actingAsUser(['name' => 'Old Name']);

    $page = visit('/de/settings/profile');

    $page->assertSee('Old Name')
        ->fill('name', 'New Browser Name')
        ->click(__('Save'))
        ->wait(1)
        ->assertSee(__('Saved.'))
        ->assertNoJavaScriptErrors();

    expect($user->refresh()->name)->toBe('New Browser Name');
});

it('shows a validation error when the profile name is cleared', function () {
    actingAsUser(['name' => 'Original']);

    $page = visit('/de/settings/profile');

    $page->fill('name', '')
        ->click(__('Save'))
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(User::query()->where('name', '')->exists())->toBeFalse();
});

it('still shows the updated name after a full page reload', function () {
    $user = actingAsUser(['name' => 'Before Reload']);

    $page = visit('/de/settings/profile');
    $page->fill('name', 'After Reload')
        ->click(__('Save'))
        ->wait(1);

    $reloaded = visit('/de/settings/profile');
    $reloaded->assertSee('After Reload')
        ->assertNoJavaScriptErrors();

    expect($user->refresh()->name)->toBe('After Reload');
});
