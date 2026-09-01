<?php

use App\Models\WebhookSubscription;

/*
|--------------------------------------------------------------------------
| Issue #36 follow-up — operator approve/reject for pending subscriptions
|--------------------------------------------------------------------------
*/

it('approves a pending subscription', function () {
    $subscription = WebhookSubscription::factory()->pending()->create();

    $this->artisan('webhook:approve', ['subscription' => $subscription->id])
        ->assertExitCode(0);

    expect($subscription->fresh())
        ->approved_at->not->toBeNull()
        ->active->toBeTrue()
        ->disabled_at->toBeNull();
});

it('refuses to approve an already approved subscription', function () {
    $subscription = WebhookSubscription::factory()->create();

    $this->artisan('webhook:approve', ['subscription' => $subscription->id])
        ->assertExitCode(1);
});

it('approves a previously rejected subscription', function () {
    $subscription = WebhookSubscription::factory()->rejected()->create();

    $this->artisan('webhook:approve', ['subscription' => $subscription->id])
        ->assertExitCode(0);

    expect($subscription->fresh())
        ->approved_at->not->toBeNull()
        ->rejected_at->toBeNull();
});

it('fails cleanly approving an unknown subscription id', function () {
    $this->artisan('webhook:approve', ['subscription' => 999999])
        ->assertExitCode(1);
});

it('requires either a subscription id or --list', function () {
    $this->artisan('webhook:approve')
        ->assertExitCode(1);
});

it('lists pending subscriptions and excludes approved and rejected ones', function () {
    $pending = WebhookSubscription::factory()->pending()->create(['url' => 'https://1.1.1.1/pending-hook']);
    WebhookSubscription::factory()->create();
    WebhookSubscription::factory()->rejected()->create();

    $this->artisan('webhook:approve', ['--list' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain((string) $pending->id);
});

it('says so when there is nothing pending to list', function () {
    WebhookSubscription::factory()->create();

    $this->artisan('webhook:approve', ['--list' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('No pending subscriptions.');
});

it('rejects a pending subscription, distinguishable from not-yet-looked-at', function () {
    $subscription = WebhookSubscription::factory()->pending()->create();

    $this->artisan('webhook:reject', ['subscription' => $subscription->id])
        ->assertExitCode(0);

    $subscription->refresh();
    expect($subscription->approved_at)->toBeNull()
        ->and($subscription->rejected_at)->not->toBeNull();

    $this->artisan('webhook:approve', ['--list' => true])
        ->expectsOutputToContain('No pending subscriptions.');
});

it('refuses to reject an already approved subscription', function () {
    $subscription = WebhookSubscription::factory()->create();

    $this->artisan('webhook:reject', ['subscription' => $subscription->id])
        ->assertExitCode(1);

    expect($subscription->fresh()->rejected_at)->toBeNull();
});

it('refuses to reject an already rejected subscription', function () {
    $subscription = WebhookSubscription::factory()->rejected()->create();

    $this->artisan('webhook:reject', ['subscription' => $subscription->id])
        ->assertExitCode(1);
});

it('fails cleanly rejecting an unknown subscription id', function () {
    $this->artisan('webhook:reject', ['subscription' => 999999])
        ->assertExitCode(1);
});
