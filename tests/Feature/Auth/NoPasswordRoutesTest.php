<?php

use App\Models\Country;
use Illuminate\Support\Facades\Route;

/**
 * The portal signs in through Nostr and LNURL only; `users.password` holds a
 * placeholder for Laravel's auth and is never asked for. These routes were the
 * starter kit's password flows and must not come back.
 */
it('registers no password route', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with(['password.request', 'password.reset', 'password.confirm', 'settings.password']);

it('serves no password page', function (string $path) {
    Country::factory()->create(['code' => 'de']);
    actingAsUser();

    $this->get($path)->assertNotFound();
})->with(['/forgot-password', '/reset-password/some-token', '/confirm-password', '/de/settings/password']);
