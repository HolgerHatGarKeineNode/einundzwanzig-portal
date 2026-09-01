<?php

namespace App\Console\Commands;

use App\Models\WebhookSubscription;
use Illuminate\Console\Command;

/**
 * The operator's decline path for a pending webhook subscription (Issue #36
 * follow-up). Sets `rejected_at`, a column independent of `approved_at`
 * (see the migration's docblock) so a decline is distinguishable from "not
 * yet looked at" — `webhook:approve --list` excludes it once rejected.
 *
 * Only ever applies to a still-pending subscription: an approved one is
 * either paused via the owner's own PATCH or removed via DELETE, not
 * "rejected" after the fact.
 */
class RejectWebhookSubscription extends Command
{
    /**
     * @var string
     */
    protected $signature = 'webhook:reject {subscription : Webhook subscription id}';

    /**
     * @var string
     */
    protected $description = 'Reject a pending webhook subscription';

    public function handle(): int
    {
        $subscription = WebhookSubscription::query()->find($this->argument('subscription'));

        if ($subscription === null) {
            $this->error('No such webhook subscription.');

            return Command::FAILURE;
        }

        if ($subscription->approved_at !== null) {
            $this->error('This subscription is already approved — reject only applies to a pending one.');

            return Command::FAILURE;
        }

        if ($subscription->rejected_at !== null) {
            $this->error('This subscription was already rejected.');

            return Command::FAILURE;
        }

        $subscription->forceFill(['rejected_at' => now()])->save();

        $this->info("Subscription #{$subscription->id} rejected.");

        return Command::SUCCESS;
    }
}
