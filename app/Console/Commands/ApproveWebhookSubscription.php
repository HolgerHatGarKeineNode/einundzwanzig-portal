<?php

namespace App\Console\Commands;

use App\Models\WebhookSubscription;
use Illuminate\Console\Command;

/**
 * The operator's approval path for a self-service webhook subscription
 * (Issue #36 follow-up) — the only thing that may set `approved_at` outside
 * the create() default, since the four owner-facing API routes deliberately
 * cannot.
 *
 * Refuses on a subscription that is already approved: this command is for
 * clearing the pending queue, not for reviving one the owner paused or the
 * system auto-disabled — those go through PATCH, per
 * RetryWebhookDeliveries' "leave re-enabling to the owner". A previously
 * rejected subscription may still be approved — an operator reviewing again
 * and changing their mind is not a special case.
 */
class ApproveWebhookSubscription extends Command
{
    /**
     * @var string
     */
    protected $signature = 'webhook:approve {subscription? : Webhook subscription id} {--list : List pending subscriptions instead of approving one}';

    /**
     * @var string
     */
    protected $description = 'Approve a pending webhook subscription, or list pending ones';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listPending();
        }

        $id = $this->argument('subscription');

        if ($id === null) {
            $this->error('Provide a subscription id to approve, or --list to see pending ones.');

            return Command::FAILURE;
        }

        $subscription = WebhookSubscription::query()->find($id);

        if ($subscription === null) {
            $this->error('No such webhook subscription.');

            return Command::FAILURE;
        }

        if ($subscription->approved_at !== null) {
            $this->error('This subscription is already approved.');

            return Command::FAILURE;
        }

        $subscription->forceFill([
            'approved_at' => now(),
            'rejected_at' => null,
            'active' => true,
            'disabled_at' => null,
        ])->save();

        $this->info("Subscription #{$subscription->id} approved.");

        return Command::SUCCESS;
    }

    private function listPending(): int
    {
        $pending = WebhookSubscription::query()
            ->pending()
            ->oldest()
            ->get(['id', 'user_id', 'url', 'resources', 'created_at']);

        if ($pending->isEmpty()) {
            $this->info('No pending subscriptions.');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'User', 'URL', 'Resources', 'Created'],
            $pending->map(fn (WebhookSubscription $subscription): array => [
                $subscription->id,
                $subscription->user_id,
                $subscription->url,
                implode(', ', $subscription->resources),
                $subscription->created_at?->toIso8601String(),
            ]),
        );

        return Command::SUCCESS;
    }
}
