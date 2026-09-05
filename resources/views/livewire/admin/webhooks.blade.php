<?php

use App\Models\WebhookSubscription;
use App\Support\BoardGate;
use App\Traits\SeoTrait;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Board-only admin view to approve, reject or revoke webhook subscriptions
 * (Issue #36's `webhooks.require_approval` gate had no UI to act on it —
 * Issue #40).
 *
 * Three operator decisions, all of them expressed in the two columns the
 * model already carries: approve sets `approved_at`; revoke clears it back to
 * null; reject sets `rejected_at` and thereby takes the subscription out of
 * {@see WebhookSubscription::scopePending()} for good — a declined request
 * must not resurface as "not yet looked at".
 *
 * `rejected_at` and the webhook:approve/webhook:reject artisan commands
 * merged into `master` after this view was first written; the earlier
 * docblock here claimed the column did not exist on this branch, which has
 * been untrue since that merge.
 */
new class extends Component
{
    use SeoTrait;

    /**
     * Stays a BoardGate check rather than an $this->authorize() call, unlike
     * the three actions below: this page's guard is about the whole pending
     * queue — every owner's URL — and there is no subscription to pass to a
     * policy ability here. WebhookSubscriptionPolicy::viewAny() could not
     * carry the rule as it stands either: it returns true for every
     * authenticated user, and nothing calls it — grepped 2026-09-04, the
     * ability has no caller in app/, routes/ or resources/ — so wiring it up
     * here without rewriting it would gate nothing. Both mechanisms ask
     * BoardGate in the end (Issue #54).
     */
    public function mount(): void
    {
        abort_unless(BoardGate::allows(auth()->user()), 403);
    }

    /**
     * Awaiting a decision — the model's own definition of "pending", which
     * excludes a rejected subscription as well as an approved one.
     *
     * @return Collection<int, WebhookSubscription>
     */
    public function getPendingProperty(): Collection
    {
        return WebhookSubscription::query()
            ->pending()
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

    /**
     * Unlocks delivery, and takes back a rejection while doing so: approving
     * is a statement that overrides an earlier decline, the same rule the
     * `webhook:approve` command follows. Without clearing `rejected_at` a row
     * could end up carrying both timestamps, and settings/webhooks.blade.php's
     * statusFor() reads `rejected_at` first — the owner would be shown
     * "Abgelehnt" for a subscription that is delivering (Issue #54). The
     * reachable route to that state is a stale render: the pending list
     * excludes rejected rows, but a second operator can reject between this
     * page's render and the click on its Freigeben button.
     *
     * `active` and `disabled_at` stay untouched here — the owner's pause
     * switch and the system's auto-disable are not the board's to flip (see
     * the migration's docblock); an approved-but-blocked subscription is
     * flagged by stillBlocked() instead.
     */
    public function approve(int $id): void
    {
        $subscription = WebhookSubscription::findOrFail($id);

        $this->authorize('approve', $subscription);

        $subscription->approved_at = now();
        $subscription->rejected_at = null;
        $subscription->save();

        session()->flash('status', __('Webhook-Subscription freigegeben.'));
    }

    /**
     * The operator's decline path, same rule as the `webhook:reject` command
     * in app/Console/Commands/RejectWebhookSubscription.php (referenced by
     * path on purpose — a {@see} with the class name makes Pint add an
     * otherwise unused import here): only a still pending subscription can be
     * declined, and the decision is recorded in `rejected_at` alone —
     * `approved_at`, the owner's `active` switch and the row itself stay
     * untouched, exactly as in revoke().
     *
     * An already decided subscription is a no-op rather than an error: the
     * only way to get here is a second click on a stale render, and the row
     * has left the pending list either way.
     */
    public function reject(int $id): void
    {
        $subscription = WebhookSubscription::findOrFail($id);

        $this->authorize('reject', $subscription);

        if ($subscription->approved_at !== null || $subscription->rejected_at !== null) {
            return;
        }

        $subscription->rejected_at = now();
        $subscription->save();

        session()->flash('status', __('Webhook-Subscription abgelehnt.'));
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
     *
     * The state comes back as a scannable label AND the sentence that
     * explains it, split on purpose (Issue #55). The two used to be one
     * string rendered inside a `flux:badge`, and a badge carries
     * `whitespace-nowrap` (flux/badge/index.blade.php:32), so that sentence
     * could not wrap at any width. Measured at a 375px viewport: the badge
     * ran to x=541.02 while its own card ended at x=335 — 206.02px of the
     * sentence outside the card and past the viewport, with
     * `documentElement.scrollWidth` still at 375, so nothing scrolled and
     * the text was simply gone.
     *
     * Label and colour are the ones settings/webhooks.blade.php shows the
     * subscription's owner for the same two states (statusFor(): 'paused',
     * 'disabled'), so the board and the owner read the same word for the
     * same condition.
     *
     * @return array{label: string, explanation: string, color: string}|null
     */
    public function stillBlocked(WebhookSubscription $subscription): ?array
    {
        if (! $subscription->active) {
            return [
                'label' => __('Pausiert'),
                'explanation' => __('Vom Besitzer pausiert — erhält trotz Freigabe keine Zustellungen.'),
                'color' => 'zinc',
            ];
        }

        if ($subscription->disabled_at !== null) {
            return [
                'label' => __('Deaktiviert'),
                'explanation' => __('Automatisch deaktiviert nach wiederholten Zustellungsfehlern.'),
                'color' => 'red',
            ];
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

    {{-- Both empty states on this page are the same kind of message and now use
         the same component (Issue #55). This one was a `flux:callout`, which is
         an attention device — a bordered, iconised block that says "look here".
         An empty approval queue is the good outcome, not an alert, and the
         approved list two headings down already stated it as quiet `flux:text`.
         The callout stays where it belongs: the flash above, which reports what
         just changed. --}}
    @if ($this->pending->isEmpty())
        <flux:text class="text-zinc-500 dark:text-zinc-400" data-testid="admin-webhooks-pending-empty">
            {{ __('Keine offenen Anfragen.') }}
        </flux:text>
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
                                {{ __('Angefragt am :date', ['date' => $subscription->created_at?->asDateTime()]) }}
                            </p>
                        </div>

                        {{-- wire:loading.attr statt Flux' eigenem data-flux-loading: der
                             Aufrufer gewinnt beim Attribut-Merge, dafuer ist der Knopf
                             waehrend des Requests wirklich nicht mehr klickbar. Das
                             wire:target dazu setzt Flux selbst aus dem wire:click, also
                             greift es pro Zeile und nicht ueber die ganze Liste. --}}
                        <div class="flex shrink-0 items-center gap-2">
                            <flux:button variant="primary" size="sm"
                                         wire:click="approve({{ $subscription->id }})"
                                         wire:loading.attr="disabled"
                                         data-testid="approve-{{ $subscription->id }}">
                                {{ __('Freigeben') }}
                            </flux:button>

                            <flux:button variant="danger" size="sm"
                                         wire:click="reject({{ $subscription->id }})"
                                         wire:loading.attr="disabled"
                                         wire:confirm="{{ __('Diese Anfrage ablehnen? Die Ablehnung lässt sich hier nicht wieder aufheben.') }}"
                                         data-testid="reject-{{ $subscription->id }}">
                                {{ __('Ablehnen') }}
                            </flux:button>
                        </div>
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
                        <div class="min-w-0" data-testid="admin-webhooks-approved-info-{{ $subscription->id }}">
                            <p class="truncate font-mono text-sm">{{ $subscription->url }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Besitzer') }}: {{ $subscription->user?->name ?? '#'.$subscription->user_id }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ implode(', ', $subscription->resources) }}
                            </p>

                            {{-- One word in the badge, the sentence beside it. The badge
                                 is what an operator scans a list for; the sentence is
                                 what they read once they have found it, and only the
                                 second one may wrap. `flex-wrap` lets the sentence drop
                                 under the badge when the column gets narrow instead of
                                 pushing it sideways; `items-baseline` sits the badge on
                                 the sentence's first line. --}}
                            @if ($blocked = $this->stillBlocked($subscription))
                                <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1"
                                     data-testid="admin-webhooks-blocked-{{ $subscription->id }}">
                                    <flux:badge size="sm" :color="$blocked['color']">{{ $blocked['label'] }}</flux:badge>
                                    <flux:text class="min-w-0 text-zinc-600 dark:text-zinc-300">
                                        {{ $blocked['explanation'] }}
                                    </flux:text>
                                </div>
                            @endif
                        </div>

                        <flux:button variant="danger" size="sm"
                                     wire:click="revoke({{ $subscription->id }})"
                                     wire:loading.attr="disabled"
                                     data-testid="revoke-{{ $subscription->id }}">
                            {{ __('Zurückziehen') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
