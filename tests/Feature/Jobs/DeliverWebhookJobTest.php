<?php

use App\Jobs\DeliverWebhookJob;
use App\Models\MeetupEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Http;

it('delivers with the documented headers and a verifiable HMAC signature', function () {
    $secret = bin2hex(random_bytes(32));
    $subscription = WebhookSubscription::factory()->create(['secret' => $secret, 'url' => 'https://1.1.1.1/hook']);
    $delivery = WebhookDelivery::factory()->create([
        'subscription_id' => $subscription->id,
        'payload' => [
            'action' => 'updated',
            'resource' => 'meetup-event',
            'id' => 7,
            'sequence' => 42,
            'occurred_at' => now()->toIso8601String(),
            'api_version' => '1',
            'data' => ['id' => 7, 'title' => 'Bitcoin Stammtisch'],
            'links' => ['self' => null],
        ],
    ]);

    Http::fake(['*' => Http::response('', 200)]);

    (new DeliverWebhookJob($subscription->id, $delivery->id))->handle();

    Http::assertSent(function ($request) use ($delivery, $secret): bool {
        $body = $request->body();
        $timestamp = $request->header('X-Portal-Timestamp')[0] ?? '';
        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $request->url() === 'https://1.1.1.1/hook'
            && $request->method() === 'POST'
            && json_decode($body, true) === $delivery->payload
            && ($request->header('X-Portal-Event')[0] ?? null) === 'meetup-event.updated'
            && ($request->header('X-Portal-Delivery')[0] ?? null) === (string) $delivery->id
            && $timestamp !== ''
            && ($request->header('X-Portal-Signature')[0] ?? null) === $expectedSignature;
    });

    $delivery->refresh();
    expect($delivery->delivered_at)->not->toBeNull()
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_response_code)->toBe(200);
});

it('never follows a redirect and treats it as a failed attempt', function () {
    $subscription = WebhookSubscription::factory()->create();
    $delivery = WebhookDelivery::factory()->create(['subscription_id' => $subscription->id]);

    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://evil.example/steal'])]);

    expect(fn () => (new DeliverWebhookJob($subscription->id, $delivery->id))->handle())
        ->toThrow(RuntimeException::class);

    Http::assertSentCount(1);

    $delivery->refresh();
    expect($delivery->delivered_at)->toBeNull()
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_response_code)->toBe(302);
});

it('throws on a non-2xx response so the queue retries it', function () {
    $subscription = WebhookSubscription::factory()->create();
    $delivery = WebhookDelivery::factory()->create(['subscription_id' => $subscription->id]);

    Http::fake(['*' => Http::response('', 500)]);

    expect(fn () => (new DeliverWebhookJob($subscription->id, $delivery->id))->handle())
        ->toThrow(RuntimeException::class);

    $delivery->refresh();
    expect($delivery->attempts)->toBe(1)
        ->and($delivery->last_response_code)->toBe(500)
        ->and($delivery->delivered_at)->toBeNull();
});

it('resets consecutive_failures on the subscription after a successful delivery', function () {
    $subscription = WebhookSubscription::factory()->create(['consecutive_failures' => 4]);
    $delivery = WebhookDelivery::factory()->create(['subscription_id' => $subscription->id]);

    Http::fake(['*' => Http::response('', 204)]);

    (new DeliverWebhookJob($subscription->id, $delivery->id))->handle();

    expect($subscription->fresh()->consecutive_failures)->toBe(0);
});

it('rejects a subscription url that now resolves to a private address, counts it as a failed delivery, and makes no HTTP call', function () {
    // Dispatched (not just handle()'d): $this->fail() only reaches the job's
    // own failed() through the queue's job wrapper, which direct handle() calls
    // do not have. QUEUE_CONNECTION=sync (phpunit.xml) runs it synchronously.
    $subscription = WebhookSubscription::factory()->create(['url' => 'https://10.0.0.9/hook']);
    $delivery = WebhookDelivery::factory()->create(['subscription_id' => $subscription->id]);

    Http::fake();

    DeliverWebhookJob::dispatch($subscription->id, $delivery->id);

    Http::assertNothingSent();

    expect($delivery->fresh()->failed_at)->not->toBeNull()
        ->and($subscription->fresh()->consecutive_failures)->toBe(1);
});

it('marks the delivery failed and increments consecutive_failures once all retries are exhausted', function () {
    $subscription = WebhookSubscription::factory()->create(['consecutive_failures' => 3]);
    $delivery = WebhookDelivery::factory()->create(['subscription_id' => $subscription->id]);

    (new DeliverWebhookJob($subscription->id, $delivery->id))->failed(new RuntimeException('gave up'));

    expect($delivery->fresh())
        ->failed_at->not->toBeNull()
        ->and($subscription->fresh())
        ->consecutive_failures->toBe(4)
        ->disabled_at->toBeNull();
});

it('auto-disables a subscription after the configured number of consecutive failed deliveries', function () {
    $subscription = WebhookSubscription::factory()->create([
        'consecutive_failures' => config('einundzwanzig.webhooks.auto_disable_after') - 1,
    ]);
    $delivery = WebhookDelivery::factory()->create(['subscription_id' => $subscription->id]);

    (new DeliverWebhookJob($subscription->id, $delivery->id))->failed(new RuntimeException('gave up'));

    expect($subscription->fresh())
        ->consecutive_failures->toBe(config('einundzwanzig.webhooks.auto_disable_after'))
        ->disabled_at->not->toBeNull();
});

it('does nothing for a subscription or delivery that no longer exists', function () {
    Http::fake();

    (new DeliverWebhookJob(999999, 999999))->handle();

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Issue #36 follow-up — a pending (unapproved) subscription is not eligible
|--------------------------------------------------------------------------
|
| ChangeRecorder::dispatchWebhooks() queries WebhookSubscription::eligibleForDelivery(),
| which requires approved_at not null — this exercises that path end to end,
| not just the scope in isolation.
|
*/
it('queues no delivery for a pending subscription', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    $subscription = WebhookSubscription::factory()->pending()->create(['resources' => ['meetup-event']]);

    Http::fake(['*' => Http::response('', 200)]);

    MeetupEvent::factory()->create();

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->where('subscription_id', $subscription->id)->count())->toBe(0);
});

it('queues no delivery for a rejected subscription', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    $subscription = WebhookSubscription::factory()->rejected()->create(['resources' => ['meetup-event']]);

    Http::fake(['*' => Http::response('', 200)]);

    MeetupEvent::factory()->create();

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->where('subscription_id', $subscription->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Regression: a deletion delivers correctly after the source row is gone
|--------------------------------------------------------------------------
|
| The whole point of Issue #36's "hard-won lesson" #1 — the job never carries
| a model, only the two row ids, and reads the delivery's own payload copy.
| QUEUE_CONNECTION=sync in phpunit.xml runs DeliverWebhookJob synchronously
| the moment ChangeRecorder dispatches it, i.e. strictly after the
| MeetupEvent row is truly gone from the database.
|
*/
it('delivers a deletion after the source MeetupEvent row is gone', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    // Faked from the start: MeetupEvent::factory()->create() below already
    // triggers a "created" delivery to the same subscription, synchronously.
    Http::fake(['*' => Http::response('', 200)]);

    $subscription = WebhookSubscription::factory()->create(['resources' => ['meetup-event']]);
    $event = MeetupEvent::factory()->create();
    $meetupId = $event->meetup_id;

    // Re-fake to reset the recorded request log and delete the "created"
    // delivery row above — the assertions below are about the deletion only.
    WebhookDelivery::query()->delete();
    Http::fake(['*' => Http::response('', 200)]);

    $event->delete();

    Http::assertSent(function ($request) use ($meetupId): bool {
        $payload = json_decode($request->body(), true);

        return $payload['action'] === 'deleted'
            && $payload['resource'] === 'meetup-event'
            && $payload['data'] === null
            && $payload['previous']['meetup_id'] === $meetupId;
    });

    $delivery = WebhookDelivery::query()->where('subscription_id', $subscription->id)->sole();
    expect($delivery->delivered_at)->not->toBeNull();
});
