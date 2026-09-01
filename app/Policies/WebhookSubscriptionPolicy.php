<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookSubscription;
use App\Support\BoardGate;

class WebhookSubscriptionPolicy
{
    /**
     * Any authenticated user may register a subscription; the config gate
     * `einundzwanzig.webhooks.require_approval` is the actual abuse brake, not
     * this check — a new subscription stays `pending` until an operator
     * approves it.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WebhookSubscription $subscription): bool
    {
        return $this->owns($user, $subscription);
    }

    public function update(User $user, WebhookSubscription $subscription): bool
    {
        return $this->owns($user, $subscription);
    }

    public function delete(User $user, WebhookSubscription $subscription): bool
    {
        return $this->owns($user, $subscription);
    }

    /**
     * Board-only: unlocks delivery by setting `approved_at` (Issue #40).
     */
    public function approve(User $user, WebhookSubscription $subscription): bool
    {
        return BoardGate::allows($user);
    }

    /**
     * Board-only: clears `approved_at` again, without touching `active` or
     * the subscription itself.
     */
    public function revoke(User $user, WebhookSubscription $subscription): bool
    {
        return BoardGate::allows($user);
    }

    private function owns(User $user, WebhookSubscription $subscription): bool
    {
        return $subscription->user_id === $user->id || $user->hasRole('super-admin');
    }
}
