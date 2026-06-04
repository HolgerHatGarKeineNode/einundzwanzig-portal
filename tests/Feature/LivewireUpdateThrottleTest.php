<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

it('registers a generous Livewire update throttle keyed by IP', function () {
    $limiter = RateLimiter::limiter('livewire');

    expect($limiter)->not->toBeNull();

    $request = Request::create('/livewire/update', 'POST');
    $request->server->set('REMOTE_ADDR', '203.0.113.10');

    $limit = $limiter($request);

    expect($limit->maxAttempts)->toBe(120)
        ->and($limit->decaySeconds)->toBe(60)
        ->and($limit->key)->toBe('203.0.113.10');
});

it('applies the livewire throttle middleware to the update route', function () {
    $route = Route::getRoutes()->getByName('livewire.update');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:livewire');
});
