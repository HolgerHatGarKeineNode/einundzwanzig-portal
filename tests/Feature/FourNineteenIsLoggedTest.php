<?php

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
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
        ->withArgs(fn (string $message, array $context): bool => $message === '419 fuer angemeldeten Nutzer'
            && $context['exception'] === $exceptionClass)
        ->once();
})->with([
    'csrf' => TokenMismatchException::class,
    'livewire snapshot' => CorruptComponentPayloadException::class,
]);

it('stays silent for anonymous requests so the bot noise does not return', function (string $exceptionClass) {
    // Die Unterdrueckung wurde wegen Scannern eingebaut, die /livewire/update mit
    // manipulierten Snapshots durchprobieren. Die bleiben still.
    Log::spy();

    app(ExceptionHandler::class)->report(new $exceptionClass('kaputt'));

    Log::shouldNotHaveReceived('warning');
})->with([
    'csrf' => TokenMismatchException::class,
    'livewire snapshot' => CorruptComponentPayloadException::class,
]);
