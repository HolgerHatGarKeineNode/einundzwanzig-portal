<?php

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

/**
 * Ein 419 war unsichtbar — genau das machte Issue #18 undiagnostizierbar.
 *
 * TokenMismatchException steht in Laravels internalDontReport,
 * CorruptComponentPayloadException haben wir selbst stummgeschaltet. Beide enden als
 * 419, und im Produktionslog stand zum Zeitpunkt des gemeldeten Fehlers nichts.
 */
it('logs a silenced 419 when the user is signed in', function (string $exceptionClass) {
    Log::spy();

    $this->actingAs(User::factory()->create());

    // Den report()-Pfad des Handlers direkt ansprechen: der Router wuerde die
    // Ausnahme sonst gar nicht erst erzeugen.
    app(ExceptionHandler::class)->report(new $exceptionClass('kaputt'));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === '419 mit Session-Cookie'
            && $context['exception'] === $exceptionClass)
        ->once();
})->with([
    'csrf' => TokenMismatchException::class,
    'livewire snapshot' => CorruptComponentPayloadException::class,
]);

it('stays silent for requests without a session cookie so the bot noise does not return', function (string $exceptionClass) {
    // Die Unterdrueckung wurde wegen Scannern eingebaut, die /livewire/update mit
    // manipulierten Snapshots durchprobieren. Die schicken kein Session-Cookie.
    Log::spy();

    app(ExceptionHandler::class)->report(new $exceptionClass('kaputt'));

    Log::shouldNotHaveReceived('warning');
})->with([
    'csrf' => TokenMismatchException::class,
    'livewire snapshot' => CorruptComponentPayloadException::class,
]);

it('logs a 419 from a browser whose session is gone but whose cookie is not', function () {
    /*
     * Der Fall, den die alte Bedingung `auth()->check()` ausschloss — und zugleich der
     * wahrscheinlichste aus Issue #18. Ist die Session serverseitig weg, ist der
     * Request nicht angemeldet; geloggt wurde deshalb nie etwas. Das Cookie ist der
     * Beleg, dass da ein echter Browser sass und kein Scanner.
     */
    Log::spy();

    $request = Request::create('/livewire/update', 'POST');
    $request->cookies->set(config('session.cookie'), 'eine-abgelaufene-id');
    app()->instance('request', $request);

    app(ExceptionHandler::class)->report(new TokenMismatchException('kaputt'));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === '419 mit Session-Cookie'
            && $context['user_id'] === null)
        ->once();
});
