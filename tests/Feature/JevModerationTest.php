<?php

use App\Services\JevModeration;
use Illuminate\Support\Facades\Http;

it('screens content and returns the verdict', function () {
    config()->set('services.jev.enabled', true);
    Http::fake(['classifier.dev' => Http::response([
        'results' => [['label' => 'spam_werbung', 'confidence' => 0.97]],
    ])]);

    $result = app(JevModeration::class)->screen('Treffen', 'Kauft jetzt billig Follower!');

    expect($result['verdict'])->toBe('spam_werbung')
        ->and($result['confidence'])->toBe(0.97)
        ->and($result['ok'])->toBeTrue();
});

it('fails open when jev is unreachable', function () {
    config()->set('services.jev.enabled', true);
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('connection refused');
    });

    $result = app(JevModeration::class)->screen('Treffen', 'Normale Beschreibung.');

    expect($result['verdict'])->toBe('jev_ausfall')->and($result['ok'])->toBeFalse();
});

it('is a no-op when the feature flag is off', function () {
    config()->set('services.jev.enabled', false);
    Http::fake();

    $result = app(JevModeration::class)->screen('Treffen', 'Normale Beschreibung.');

    expect($result['verdict'])->toBe('disabled')->and($result['ok'])->toBeFalse();
    Http::assertNothingSent();
});
