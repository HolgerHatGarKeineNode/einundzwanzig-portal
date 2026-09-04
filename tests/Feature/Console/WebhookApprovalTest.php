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

    // Only `approved_at` is asserted here. This row carries the factory defaults
    // for `active` (true) and `disabled_at` (null), so asserting those two would
    // be satisfied by the seed whatever the command writes — measured: with them
    // in place, a command that force-fills both stays green. They are asserted in
    // the next test instead, on a row where they differ from the default.
    expect($subscription->fresh())->approved_at->not->toBeNull();
});

/**
 * Issue #54: until then the command also forced `active` to true and
 * `disabled_at` to null, contradicting its own docblock and the migration's
 * "only the owner clears it again (PATCH)" — approving a subscription the
 * owner had paused, or the system had auto-disabled, silently released the
 * brake while leaving `consecutive_failures` untouched.
 *
 * The seed is deliberately the OPPOSITE of the factory default on both
 * columns (default: active => true, disabled_at => null). That is the whole
 * point of this test: on a default row the two assertions hold no matter what
 * the command does, which is how the old version of the assertion above could
 * not tell the pre-#54 command from the current one.
 */
it('approves a pending subscription without un-pausing or un-disabling it', function () {
    $subscription = WebhookSubscription::factory()->pending()->disabled()->create(['active' => false]);

    $this->artisan('webhook:approve', ['subscription' => $subscription->id])
        ->assertExitCode(0);

    expect($subscription->fresh())
        ->approved_at->not->toBeNull()
        ->active->toBeFalse()
        ->disabled_at->not->toBeNull()
        ->consecutive_failures->toBe(10);

    // Approved on the board's side, still not delivering — the owner's pause and
    // the auto-disable are separate gates, and only the owner reopens them.
    expect(WebhookSubscription::query()->eligibleForDelivery()->whereKey($subscription->id)->exists())->toBeFalse();
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
