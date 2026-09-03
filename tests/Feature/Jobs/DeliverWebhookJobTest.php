<?php

use App\Jobs\DeliverWebhookJob;
use App\Models\ApiChange;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Support\Broadcasting\ChangeRecorder;
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

/*
|--------------------------------------------------------------------------
| Issue #36 gap closure — meetup CUD, not just meetup-event
|--------------------------------------------------------------------------
|
| Every test above exercises `meetup-event` only. `meetup` is an independent
| entry in ChangeRecorder::RESOURCES with its own resource class and payload
| shape — a filter or envelope bug scoped to `meetup` would sail through
| every test above unnoticed. These assert the POSTed body against the
| stored api_changes row itself, not a hand-built expectation, so a payload
| built from the wrong resource class or a stale attribute would show up as
| a mismatch here.
*/

it('delivers a meetup creation with the envelope identical to the stored api_changes row', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    Http::fake(['*' => Http::response('', 200)]);

    $subscription = WebhookSubscription::factory()->create(['resources' => ['meetup']]);

    Meetup::factory()->create();

    $change = ApiChange::query()->where('resource', 'meetup')->where('action', 'created')->sole();

    Http::assertSent(function ($request) use ($change): bool {
        return json_decode($request->body(), true) === $change->payload
            && ($request->header('X-Portal-Event')[0] ?? null) === 'meetup.created';
    });

    $delivery = WebhookDelivery::query()->where('subscription_id', $subscription->id)->sole();
    expect($delivery->delivered_at)->not->toBeNull();
});

it('delivers a meetup update with the envelope identical to the stored api_changes row', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    Http::fake(['*' => Http::response('', 200)]);

    $subscription = WebhookSubscription::factory()->create(['resources' => ['meetup']]);
    $meetup = Meetup::factory()->create();

    // Re-fake to discard the "created" delivery above — this test is about
    // the update only.
    WebhookDelivery::query()->delete();
    Http::fake(['*' => Http::response('', 200)]);

    $meetup->update(['intro' => 'Neuer Einleitungstext.']);

    $change = ApiChange::query()->where('resource', 'meetup')->where('action', 'updated')->sole();

    Http::assertSent(function ($request) use ($change): bool {
        return json_decode($request->body(), true) === $change->payload
            && ($request->header('X-Portal-Event')[0] ?? null) === 'meetup.updated';
    });

    $delivery = WebhookDelivery::query()->where('subscription_id', $subscription->id)->sole();
    expect($delivery->delivered_at)->not->toBeNull();
});

it('delivers a meetup deletion with the envelope identical to the stored api_changes row', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    Http::fake(['*' => Http::response('', 200)]);

    $subscription = WebhookSubscription::factory()->create(['resources' => ['meetup']]);
    $meetup = Meetup::factory()->create();

    WebhookDelivery::query()->delete();
    Http::fake(['*' => Http::response('', 200)]);

    $meetup->delete();

    $change = ApiChange::query()->where('resource', 'meetup')->where('action', 'deleted')->sole();

    Http::assertSent(function ($request) use ($change): bool {
        $payload = json_decode($request->body(), true);

        return $payload === $change->payload
            && $payload['action'] === 'deleted'
            && $payload['data'] === null
            && ($request->header('X-Portal-Event')[0] ?? null) === 'meetup.deleted';
    });

    $delivery = WebhookDelivery::query()->where('subscription_id', $subscription->id)->sole();
    expect($delivery->delivered_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Issue #36 gap closure — resource filtering
|--------------------------------------------------------------------------
|
| dispatchWebhooks() filters on `whereJsonContains('resources', $resource)`.
| Every existing test uses a subscription whose `resources` already covers
| whatever it creates, so a broken filter (e.g. matching on the wrong
| column, or dropping the whereJsonContains clause entirely) would never
| have shown up.
*/

it('delivers a meetup change to a meetup-only subscription but nothing for a meetup-event change', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    Http::fake(['*' => Http::response('', 200)]);

    $subscription = WebhookSubscription::factory()->create(['resources' => ['meetup']]);

    /*
     * Fixed point for Meetup::recalculateActivity(): adding a single future
     * MeetupEvent below recomputes is_active=true (hasFutureEvent) and
     * last_event_at=null (no past event exists to set it). Starting the
     * meetup at exactly that state means recalculateActivity finds nothing
     * dirty and does NOT itself fire a manual "meetup updated" record — which
     * would otherwise confound the "nothing for meetup-event" assertion below
     * (recalculateActivity calls ChangeRecorder::record() directly, bypassing
     * the observer, see Meetup::recalculateActivity()).
     */
    $meetup = Meetup::factory()->create(['is_active' => true, 'last_event_at' => null]);

    Http::assertSentCount(1);
    expect(WebhookDelivery::query()->where('subscription_id', $subscription->id)->count())->toBe(1);

    WebhookDelivery::query()->delete();
    Http::fake(['*' => Http::response('', 200)]);

    // Explicit meetup_id (no nested Meetup::factory()), future start (matches
    // the fixed point above), no recurrence — isolates this to a pure
    // "meetup-event" change with no side effect on the parent meetup.
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'recurrence_type' => null,
        'recurrence_end_date' => null,
    ]);

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->where('subscription_id', $subscription->id)->count())->toBe(0);
    // Sanity: recalculateActivity ran (observer always fires on save) and
    // genuinely found no change, it was not skipped.
    expect($meetup->fresh()->is_active)->toBeTrue()
        ->and($meetup->fresh()->last_event_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Issue #36 gap closure — mute and kill-switch stop the webhook path itself
|--------------------------------------------------------------------------
|
| ChangeRecorderTest and ResourceChangedTest already prove that no
| api_changes row is written and no ResourceChanged broadcast fires under
| muted() or with the kill switch off. Neither asserts anything about
| WebhookDelivery/DeliverWebhookJob directly — dispatchWebhooks() is only
| reached from inside record(), after the api_changes row exists, so those
| tests establish this indirectly at best. These assert the webhook path
| itself: an eligible subscription in place, Http::fake(), and a direct
| check that no request went out and no delivery row was queued.
*/

it('dispatches no webhook while the kill switch is off, even with an eligible subscription in place', function () {
    config()->set('einundzwanzig.change_log.enabled', false);
    Http::fake(['*' => Http::response('', 200)]);

    WebhookSubscription::factory()->create(['resources' => ['meetup']]);

    Meetup::factory()->create();

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->count())->toBe(0);
});

it('dispatches no webhook for changes made inside a muted block, even with an eligible subscription in place', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    Http::fake(['*' => Http::response('', 200)]);

    WebhookSubscription::factory()->create(['resources' => ['meetup']]);

    ChangeRecorder::muted(function (): void {
        Meetup::factory()->create();
    });

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->count())->toBe(0);
});
