<?php

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

it('verifies the email when the signed URL is correct', function () {
    Event::fake();
    $user = actingAsUser(['email_verified_at' => null]);

    $verifyUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->get($verifyUrl)->assertRedirect();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('does not re-fire the Verified event when email is already verified', function () {
    Event::fake();
    $user = actingAsUser(['email_verified_at' => now()]);

    $verifyUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->get($verifyUrl)->assertRedirect();

    Event::assertNotDispatched(Verified::class);
});

it('rejects an invalid signed URL with 403', function () {
    actingAsUser(['email_verified_at' => null]);

    $this->get(route('verification.verify', ['id' => 1, 'hash' => 'invalid']))
        ->assertForbidden();
});
