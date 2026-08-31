<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Support\Webhooks\SsrfGuard;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * One HTTP attempt of one queued webhook delivery (Issue #36).
 *
 * DELIBERATELY NO ELOQUENT MODEL IN THE CONSTRUCTOR — only the two row ids.
 * This generalises the same lesson App\Events\ResourceChanged documents for
 * the broadcast: {@see BroadcastEvent} sets
 * `deleteWhenMissingModels = true`, and any ShouldQueue job that holds a
 * SerializesModels property to a since-deleted Eloquent model gets dropped
 * the same silent way. Re-querying by id here means a deleted subscription or
 * delivery row is simply "nothing to do", never a silent no-op job.
 *
 * The payload itself needs no re-deriving either: {@see WebhookDelivery::$payload}
 * is a standalone copy of the api_changes envelope made at queue time — the
 * job never touches the source model (Meetup/MeetupEvent), so a `deleted`
 * delivers correctly no matter how long ago the source row was removed.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries;

    public function __construct(
        public readonly int $subscriptionId,
        public readonly int $deliveryId,
    ) {
        $this->tries = count($this->backoff()) + 1;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return config('einundzwanzig.webhooks.backoff_seconds', [60, 300, 1800, 7200, 21600]);
    }

    public function handle(): void
    {
        $subscription = WebhookSubscription::query()->find($this->subscriptionId);
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($subscription === null || $delivery === null || $delivery->delivered_at !== null) {
            return;
        }

        /*
         * Re-checked here, not just at subscription create/update time: a hostname
         * that resolved to a public IP when approved can be repointed to an
         * internal one afterwards (DNS rebinding). $this->fail() marks this
         * delivery failed immediately without spending the retry schedule on a
         * target that stays blocked either way, but still counts as one failed
         * delivery towards auto-disable via failed() below.
         */
        if (! SsrfGuard::isPublicUrl($subscription->url)) {
            $this->fail(new RuntimeException('Webhook target blocked by the SSRF guard'));

            return;
        }

        $rawBody = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;

        try {
            $response = Http::withBody($rawBody, 'application/json')
                ->withHeaders([
                    'X-Portal-Event' => sprintf('%s.%s', $delivery->payload['resource'], $delivery->payload['action']),
                    'X-Portal-Delivery' => (string) $delivery->id,
                    'X-Portal-Timestamp' => $timestamp,
                    // GitHub-style X-Hub-Signature-256 semantics, timestamp mixed in
                    // against replay — exactly the formula documented to subscribers.
                    'X-Portal-Signature' => hash_hmac('sha256', $timestamp.'.'.$rawBody, $subscription->secret),
                ])
                // Never follow a redirect, cross-host or not (Issue #36 security
                // requirement) — simplest correct reading of "don't follow
                // cross-host redirects": a 3xx is scored as a failed attempt below,
                // exactly like any other non-2xx response.
                ->withOptions(['allow_redirects' => false])
                ->timeout(config('einundzwanzig.webhooks.timeout_seconds', 10))
                ->post($subscription->url);
        } catch (ConnectionException $e) {
            $delivery->increment('attempts');

            throw $e;
        }

        $delivery->increment('attempts');
        $delivery->forceFill(['last_response_code' => $response->status()])->save();

        if ($response->successful()) {
            $delivery->forceFill(['delivered_at' => now()])->save();

            if ($subscription->consecutive_failures > 0) {
                $subscription->forceFill(['consecutive_failures' => 0])->save();
            }

            return;
        }

        throw new RuntimeException("Webhook delivery received HTTP {$response->status()}");
    }

    /**
     * Every retry (backoff() above) is exhausted, or the SSRF guard rejected the
     * target outright. One failed DELIVERY, not one failed HTTP attempt — that
     * is what `consecutive_failures` counts towards the 10-in-a-row auto-disable
     * (Issue #36's "across events, not just one event's retries").
     */
    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery !== null && $delivery->delivered_at === null) {
            $delivery->forceFill(['failed_at' => now()])->save();
        }

        $subscription = WebhookSubscription::query()->find($this->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $subscription->increment('consecutive_failures');
        $subscription->refresh();

        $autoDisableAfter = config('einundzwanzig.webhooks.auto_disable_after', 10);

        if ($subscription->disabled_at === null && $subscription->consecutive_failures >= $autoDisableAfter) {
            $subscription->forceFill(['disabled_at' => now()])->save();

            Log::error('Webhook subscription auto-disabled after repeated failures', [
                'subscription_id' => $subscription->id,
                'consecutive_failures' => $subscription->consecutive_failures,
            ]);
        }
    }
}
