<?php

use App\Models\WebhookSubscription;
use App\Support\BoardGate;
use App\Traits\SeoTrait;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Board-only admin view to approve or revoke webhook subscriptions (Issue
 * #36's `webhooks.require_approval` gate had no UI to act on it — Issue #40).
 *
 * Two states only, per the issue's acceptance criteria: approve sets
 * `approved_at`; revoke clears it back to null. This branch was cut from
 * `buzz/master` before PR be709e1a (branch
 * issue/0945941d-webhook-subscriptions-can-be-gated-but-n, adding a separate
 * `rejected_at` column and webhook:approve/webhook:reject artisan commands)
 * had merged, so there is no `rejected_at` column here to reconcile with —
 * see this branch's PR body for the full reconciliation note.
 */
new class extends Component
{
    use SeoTrait;

    public function mount(): void
    {
        abort_unless(BoardGate::allows(auth()->user()), 403);
    }

    /**
     * @return Collection<int, WebhookSubscription>
     */
    public function getPendingProperty(): Collection
    {
        return WebhookSubscription::query()
            ->whereNull('approved_at')
            ->with('user')
            ->oldest()
            ->get();
    }

    /**
     * @return Collection<int, WebhookSubscription>
     */
    public function getApprovedProperty(): Collection
    {
        return WebhookSubscription::query()
            ->whereNotNull('approved_at')
            ->with('user')
            ->latest('approved_at')
            ->get();
    }

    public function approve(int $id): void
    {
        $subscription = WebhookSubscription::findOrFail($id);

        $this->authorize('approve', $subscription);

        $subscription->approved_at = now();
        $subscription->save();

        session()->flash('status', __('Webhook-Subscription freigegeben.'));
    }

    /**
     * Clears the approval without touching the subscription itself, its
     * owner's `active` pause switch, or its delivery history — a revoked
     * subscription simply stops being eligibleForDelivery() again, the same
     * gate a brand-new subscription starts behind.
     */
    public function revoke(int $id): void
    {
        $subscription = WebhookSubscription::findOrFail($id);

        $this->authorize('revoke', $subscription);

        $subscription->approved_at = null;
        $subscription->save();

        session()->flash('status', __('Freigabe zurückgezogen.'));
    }

    /**
     * Whether an approved subscription would actually deliver right now —
     * an operator approving one the owner has paused, or the system has
     * auto-disabled, should not be told that flipped a switch it did not.
     */
    public function stillBlocked(WebhookSubscription $subscription): ?string
    {
        if (! $subscription->active) {
            return __('Vom Besitzer pausiert — erhält trotz Freigabe keine Zustellungen.');
        }

        if ($subscription->disabled_at !== null) {
            return __('Automatisch deaktiviert nach wiederholten Zustellungsfehlern.');
        }

        return null;
    }
}; ?>

<div class="mx-auto w-full max-w-4xl p-4">
    <flux:heading size="xl">{{ __('Webhook-Freigaben') }}</flux:heading>

    <flux:text class="mt-2 max-w-prose">
        {{ __('Neue Webhook-Subscriptions bleiben inaktiv, bis ein Vorstandsmitglied sie hier freischaltet.') }}
    </flux:text>

    @if (session('status'))
        <flux:callout variant="success" class="mt-4">{{ session('status') }}</flux:callout>
    @endif

    <flux:heading size="lg" class="mt-8 mb-4">
        {{ __('Wartet auf Freigabe (:count)', ['count' => $this->pending->count()]) }}
    </flux:heading>

    @if ($this->pending->isEmpty())
        <flux:callout data-testid="admin-webhooks-pending-empty">
            {{ __('Keine offenen Anfragen.') }}
        </flux:callout>
    @else
        <div class="flex flex-col gap-4" data-testid="admin-webhooks-pending-list">
            @foreach ($this->pending as $subscription)
                <div wire:key="pending-{{ $subscription->id }}"
                     class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm">{{ $subscription->url }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Besitzer') }}: {{ $subscription->user?->name ?? '#'.$subscription->user_id }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ implode(', ', $subscription->resources) }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Angefragt am :date', ['date' => $subscription->created_at?->format('d.m.Y H:i')]) }}
                            </p>
                        </div>

                        <flux:button variant="primary" size="sm"
                                     wire:click="approve({{ $subscription->id }})"
                                     data-testid="approve-{{ $subscription->id }}">
                            {{ __('Freigeben') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:separator class="my-8" />

    <flux:heading size="lg" class="mb-4">
        {{ __('Freigeschaltet (:count)', ['count' => $this->approved->count()]) }}
    </flux:heading>

    @if ($this->approved->isEmpty())
        <flux:text class="text-zinc-500 dark:text-zinc-400">
            {{ __('Noch keine Subscription freigeschaltet.') }}
        </flux:text>
    @else
        <div class="flex flex-col gap-4" data-testid="admin-webhooks-approved-list">
            @foreach ($this->approved as $subscription)
                <div wire:key="approved-{{ $subscription->id }}"
                     class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm">{{ $subscription->url }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Besitzer') }}: {{ $subscription->user?->name ?? '#'.$subscription->user_id }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ implode(', ', $subscription->resources) }}
                            </p>

                            @if ($blocked = $this->stillBlocked($subscription))
                                <flux:badge size="sm" color="amber" class="mt-2">{{ $blocked }}</flux:badge>
                            @endif
                        </div>

                        <flux:button variant="danger" size="sm"
                                     wire:click="revoke({{ $subscription->id }})"
                                     data-testid="revoke-{{ $subscription->id }}">
                            {{ __('Zurückziehen') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
