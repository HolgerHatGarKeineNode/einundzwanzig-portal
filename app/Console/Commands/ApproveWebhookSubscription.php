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
 *
 * Writes the two operator columns and nothing else, the same pair the admin
 * UI's approve() writes (resources/views/livewire/admin/webhooks.blade.php).
 * Until Issue #54 it also forced `active` to true and `disabled_at` to null,
 * which contradicted the paragraph above as well as the migration's "only the
 * owner clears it again (PATCH)": on a new subscription those two writes
 * changed nothing (both start that way), and on the one row where they did —
 * a subscription the owner paused, or one the system auto-disabled and an
 * operator then revoked back into the pending queue — approving silently
 * released the brake, while leaving `consecutive_failures` untouched so the
 * next failure disabled it straight away.
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
