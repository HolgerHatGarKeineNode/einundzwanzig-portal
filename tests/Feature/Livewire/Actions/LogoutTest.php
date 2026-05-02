<?php

use App\Livewire\Actions\Logout;

it('logs the authenticated user out and redirects to /', function () {
    actingAsUser();

    expect(auth()->check())->toBeTrue();

    $response = (new Logout)();

    expect($response->getTargetUrl())->toBe(url('/'));
    expect(auth()->check())->toBeFalse();
});

it('still produces a redirect when invoked without an authenticated session', function () {
    $response = (new Logout)();

    expect($response->getTargetUrl())->toBe(url('/'));
});

it('is registered for the POST /logout route', function () {
    actingAsUser();

    $this->post('/logout')->assertRedirect('/');
});
