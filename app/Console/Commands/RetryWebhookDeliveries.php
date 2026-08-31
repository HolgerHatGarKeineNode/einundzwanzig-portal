<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookSubscription;
use Illuminate\Console\Command;

/**
 * The operator's recovery tool for a webhook subscription's exhausted
 * deliveries (Issue #36) — re-queues everything DeliverWebhookJob gave up on
 * (`failed_at` set, `delivered_at` still null).
 *
 * Deliberately refuses on a subscription that is still paused or auto-disabled:
 * re-enabling is the owner's own PATCH (`active: true`), documented as the
 * "leave re-enabling to the owner" recovery path. Requeuing behind their back
 * would defeat the point of the auto-disable.
 */
class RetryWebhookDeliveries extends Command
{
    /**
     * @var string
     */
    protected $signature = 'webhook:retry {subscription : Webhook subscription id}';

    /**
     * @var string
     */
    protected $description = 'Re-queue a webhook subscription\'s failed deliveries';

    public function handle(): int
    {
        $subscription = WebhookSubscription::query()->find($this->argument('subscription'));

        if ($subscription === null) {
            $this->error('No such webhook subscription.');

            return Command::FAILURE;
        }

        if ($subscription->approved_at === null || ! $subscription->active || $subscription->disabled_at !== null) {
            $this->error('This subscription is pending, paused or disabled — re-enable it first (PATCH), then retry.');

            return Command::FAILURE;
        }

        $deliveries = $subscription->deliveries()
            ->whereNotNull('failed_at')
            ->whereNull('delivered_at')
            ->get();

        foreach ($deliveries as $delivery) {
            $delivery->forceFill(['failed_at' => null])->save();

            DeliverWebhookJob::dispatch($subscription->id, $delivery->id);
        }

        $this->info("{$deliveries->count()} delivery(ies) re-queued.");

        return Command::SUCCESS;
    }
}
