<?php

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Queue;

it('re-queues a subscriptions failed deliveries and clears their failed_at', function () {
    Queue::fake();

    $subscription = WebhookSubscription::factory()->create();
    $failed = WebhookDelivery::factory()->failed()->create(['subscription_id' => $subscription->id]);
    $delivered = WebhookDelivery::factory()->create([
        'subscription_id' => $subscription->id,
        'delivered_at' => now(),
    ]);

    $this->artisan('webhook:retry', ['subscription' => $subscription->id])
        ->assertExitCode(0);

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->deliveryId === $failed->id);
    Queue::assertNotPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->deliveryId === $delivered->id);

    expect($failed->fresh()->failed_at)->toBeNull();
});

it('refuses to retry a disabled subscription', function () {
    Queue::fake();

    $subscription = WebhookSubscription::factory()->disabled()->create();
    $failed = WebhookDelivery::factory()->failed()->create(['subscription_id' => $subscription->id]);

    $this->artisan('webhook:retry', ['subscription' => $subscription->id])
        ->assertExitCode(1);

    Queue::assertNothingPushed();
    expect($failed->fresh()->failed_at)->not->toBeNull();
});

it('fails cleanly for an unknown subscription id', function () {
    $this->artisan('webhook:retry', ['subscription' => 999999])
        ->assertExitCode(1);
});
