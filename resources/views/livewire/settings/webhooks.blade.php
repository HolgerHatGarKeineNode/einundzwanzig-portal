<?php

use App\Attributes\SeoDataAttribute;
use App\Models\WebhookSubscription;
use App\Rules\PublicHttpsUrl;
use App\Traits\SeoTrait;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Self-service outbound webhook subscriptions (Issue #36).
 *
 * Mirrors WebhookSubscriptionController's rules and defaults rather than
 * calling it: this is a second, independent entry point onto the same model,
 * the same way meetups/services have separate create/edit Livewire pages
 * next to their API controllers.
 */
new
#[SeoDataAttribute(key: 'settings_webhooks')]
class extends Component
{
    use SeoTrait;

    public string $url = '';

    /** @var array<int, string> */
    public array $resources = [];

    public bool $revealSecret = false;

    /**
     * The plain-text secret, shown once right after creation — same as the
     * API's store() response, regardless of the reveal-secret flag.
     */
    public ?string $plainTextSecret = null;

    /** The subscription being edited, or null. */
    #[Locked]
    public ?int $editingId = null;

    public string $editUrl = '';

    /** @var array<int, string> */
    public array $editResources = [];

    public bool $editRevealSecret = false;

    /**
     * Create a webhook subscription for the authenticated user.
     */
    public function createSubscription(): void
    {
        $this->authorize('create', WebhookSubscription::class);

        $validated = $this->validate([
            'url' => ['required', 'string', 'max:2048', new PublicHttpsUrl],
            'resources' => ['required', 'array', 'min:1'],
            'resources.*' => ['string', Rule::in(config('einundzwanzig.webhooks.allowed_resources'))],
        ]);

        $secret = bin2hex(random_bytes(32));

        WebhookSubscription::create([
            'user_id' => auth()->id(),
            'url' => $validated['url'],
            'secret' => $secret,
            'reveal_secret' => $this->revealSecret,
            'resources' => $validated['resources'],
            'approved_at' => config('einundzwanzig.webhooks.require_approval', true) ? null : now(),
            'active' => true,
            'consecutive_failures' => 0,
        ]);

        $this->plainTextSecret = $secret;

        $this->reset(['url', 'resources', 'revealSecret']);

        $this->dispatch('webhook-created');
    }

    /**
     * Dismiss the one-time plain-text secret display.
     */
    public function dismissPlainTextSecret(): void
    {
        $this->plainTextSecret = null;
    }

    /**
     * Open the inline editor for one of the authenticated user's subscriptions.
     */
    public function edit(int $id): void
    {
        $subscription = $this->ownSubscriptionOrFail($id);

        $this->authorize('update', $subscription);

        $this->editingId = $subscription->id;
        $this->editUrl = $subscription->url;
        $this->editResources = $subscription->resources;
        $this->editRevealSecret = $subscription->reveal_secret;

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editUrl = '';
        $this->editResources = [];
        $this->editRevealSecret = false;
        $this->resetValidation();
    }

    /**
     * Save the URL, resources and reveal-secret flag for the subscription
     * being edited.
     */
    public function save(): void
    {
        $subscription = $this->ownSubscriptionOrFail($this->editingId);

        $this->authorize('update', $subscription);

        $validated = $this->validate([
            'editUrl' => ['required', 'string', 'max:2048', new PublicHttpsUrl],
            'editResources' => ['required', 'array', 'min:1'],
            'editResources.*' => ['string', Rule::in(config('einundzwanzig.webhooks.allowed_resources'))],
        ]);

        $subscription->url = $validated['editUrl'];
        $subscription->resources = $validated['editResources'];
        $subscription->reveal_secret = $this->editRevealSecret;
        $subscription->save();

        $this->cancelEdit();

        session()->flash('status', __('Webhook aktualisiert.'));
    }

    /**
     * Pause or resume delivery. Resuming a system-disabled subscription also
     * clears its failure count — the same recovery path the API offers.
     */
    public function toggleActive(int $id): void
    {
        $subscription = $this->ownSubscriptionOrFail($id);

        $this->authorize('update', $subscription);

        $active = ! $subscription->active;

        if ($active && $subscription->disabled_at !== null) {
            $subscription->disabled_at = null;
            $subscription->consecutive_failures = 0;
        }

        $subscription->active = $active;
        $subscription->save();
    }

    public function deleteSubscription(int $id): void
    {
        $subscription = $this->ownSubscriptionOrFail($id);

        $this->authorize('delete', $subscription);

        $subscription->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        session()->flash('status', __('Webhook gelöscht.'));
    }

    /**
     * A single, human-facing label for the states that matter to an owner —
     * the same rule WebhookSubscriptionResource::status() uses. Checked
     * before the `approved_at === null` branch below, since a rejected
     * subscription also has a null `approved_at` — the two only differ in
     * `rejected_at` (Issue #36 follow-up).
     */
    public function statusFor(WebhookSubscription $subscription): string
    {
        if ($subscription->rejected_at !== null) {
            return 'rejected';
        }

        if ($subscription->approved_at === null) {
            return 'pending';
        }

        if ($subscription->disabled_at !== null) {
            return 'disabled';
        }

        return $subscription->active ? 'active' : 'paused';
    }

    /**
     * Product wording for one of `webhooks.allowed_resources`' slugs.
     *
     * The config holds database names (`meetup-event`); a reader of this page
     * has never seen that word anywhere else in the portal, which calls the
     * thing a Meetup-Termin — including in this page's own subheading. Going
     * through __() also gets the label translated, which the raw slug never
     * was: it read identically in all nine locales.
     *
     * An unknown slug falls back to itself, so a resource added to the config
     * without a label here still shows up (technically worded) instead of
     * silently rendering as an empty checkbox label.
     */
    public function resourceLabel(string $resource): string
    {
        return match ($resource) {
            'meetup' => __('Meetup'),
            'meetup-event' => __('Meetup-Termin'),
            default => $resource,
        };
    }

    private function ownSubscriptionOrFail(?int $id): WebhookSubscription
    {
        return WebhookSubscription::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);
    }

    public function with(): array
    {
        return [
            'subscriptions' => WebhookSubscription::query()
                ->where('user_id', auth()->id())
                ->latest()
                ->get(),
            'allowedResources' => config('einundzwanzig.webhooks.allowed_resources', []),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Webhooks')"
                       :subheading="__('Erhalte eine HTTP-Benachrichtigung, sobald sich ein Meetup oder ein Meetup-Termin ändert.')">

        <div class="space-y-8">
            {{-- Issue #54: the paragraph named WHO approves but left "and then?"
                 unanswered. Deliberately no turnaround time — nobody can hold one,
                 and an invented "usually a few days" is a promise the reader would
                 measure us against. What replaces it is an address, so the silence
                 is a stated choice with a way out rather than a gap. --}}
            <div class="space-y-3">
                <flux:text>
                    {{ __('Eine Webhook-Subscription sendet einen signierten HTTP-POST an deine URL, sobald sich eine der ausgewählten Ressourcen ändert. Neue Subscriptions müssen vom Vorstand des EINUNDZWANZIG e.V. freigeschaltet werden, bevor sie Zustellungen erhalten.') }}
                </flux:text>

                @php
                    $contactNpub = config('einundzwanzig.webhooks.contact_npub');
                @endphp

                {{-- Sentence and address stand or fall TOGETHER. With the key empty, the
                     sentence used to render on its own and end in a dangling colon —
                     measured 2026-09-04, an unfulfilled promise, which is worse than the
                     gap issue #54 started from. There is deliberately no fallback wording
                     either: a sentence that announces "we promise no turnaround" and then
                     offers no way to ask states the omission and leaves the reader exactly
                     where they were. That is the same information as silence, with more
                     noise. So an empty key means the paragraph above is the whole answer,
                     as it was before this issue. --}}
                @if ($contactNpub)
                    <flux:text>
                        {{ __('Eine feste Bearbeitungszeit nennen wir nicht — wir könnten sie nicht zusagen. Wenn du nichts hörst, frag per Nostr-DM nach:') }}
                    </flux:text>

                    {{-- The address is the control: clicking the npub copies the npub,
                         so there is no separate copy button and no icon to explain.
                         A real <button> rather than the <code role="button"> used by
                         x-nostr-calendar-address. When this was written, that Alpine
                         directive bound `click` only, so on a non-button element Enter
                         and Space did nothing (WCAG 2.1.1). #80 has since given the
                         directive keyboard handling, so that is no longer the reason —
                         a button still gets focus, role and keyboard semantics for free
                         rather than by declaration, which is why this one stays a button.

                         `break-all` because an npub is 63 characters with no break
                         opportunity. Measured 2026-09-04: unwrapped it is 555px wide,
                         so in a 375px viewport it would set the page's minimum width
                         and force horizontal scroll. With break-all it is 327px and the
                         document stays at 375.

                         The BORDER, not the fill, is what identifies this as a control
                         (WCAG 1.4.11 wants 3:1 for that). A fill cannot do the job here:
                         Flux's dark page background IS zinc-800 in this build — measured
                         rgb(38,38,38) for both — so `dark:bg-zinc-800` rendered a panel
                         with a contrast of 1.00:1 against its own page, i.e. no panel at
                         all. zinc-500 measures 4.74:1 against the light page and 3.19:1
                         against the dark one. The fill is kept for hover only, where it
                         is feedback rather than identification. --}}
                    <div x-data class="space-y-2">
                        <button type="button"
                                data-testid="webhook-contact-npub"
                                x-copy-to-clipboard="'{{ $contactNpub }}'"
                                title="{{ __('In die Zwischenablage kopieren') }}"
                                class="block w-full break-all rounded-lg border border-zinc-500 p-3 text-left font-mono text-sm text-zinc-800 transition-colors hover:bg-zinc-100 dark:text-zinc-100 dark:hover:bg-zinc-700">{{ $contactNpub }}</button>

                        {{-- `underline` and `py-1` are not decoration. Measured 2026-09-04:
                             a flux:link renders with text-decoration `none` and no external
                             icon, so on its own line it would be a link identified by colour
                             alone (WCAG 1.4.1), and it measured 19px tall against the 24px
                             floor of WCAG 2.5.8 — whose "inline" exception does not cover a
                             link that is its own block. The underline matches the docs page,
                             which underlines every outbound link. --}}
                        <flux:link :href="'https://njump.me/'.$contactNpub" external variant="subtle"
                                   class="inline-block py-1 text-sm underline underline-offset-4">
                            {{ __('Profil auf njump öffnen') }}
                        </flux:link>
                    </div>
                @endif
            </div>

            @if (session('status'))
                <flux:callout variant="success">{{ session('status') }}</flux:callout>
            @endif

            {{-- One-time secret reveal after creation --}}
            @if ($plainTextSecret)
                <flux:callout variant="success" icon="key" x-data="{ copied: false }">
                    <flux:callout.heading>{{ __('Dein neues Webhook-Secret') }}</flux:callout.heading>
                    <flux:callout.text>
                        <p class="mb-3">
                            {{ __('Kopiere das Secret jetzt. Ohne aktivierte Anzeige (siehe unten) wird es dir nur dieses eine Mal gezeigt.') }}
                        </p>
                        <div class="flex items-center gap-2">
                            <flux:input x-ref="secret" readonly value="{{ $plainTextSecret }}" class="font-mono" />
                            <flux:button type="button" icon="clipboard-document"
                                         x-on:click="navigator.clipboard.writeText($refs.secret.value); copied = true; setTimeout(() => copied = false, 2000)">
                                <span x-show="!copied">{{ __('Kopieren') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Kopiert!') }}</span>
                            </flux:button>
                        </div>
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button variant="ghost" size="sm" wire:click="dismissPlainTextSecret">
                            {{ __('Verstanden') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            {{-- Create subscription form --}}
            <form wire:submit="createSubscription" class="space-y-4">
                <flux:input wire:model="url"
                            type="url"
                            :label="__('Ziel-URL')"
                            placeholder="https://example.com/webhooks/einundzwanzig"
                            :description="__('Muss https sein und öffentlich erreichbar (kein localhost, kein privates Netz).')" />

                <flux:checkbox.group wire:model="resources" :label="__('Ressourcen')">
                    @foreach ($allowedResources as $resource)
                        <flux:checkbox value="{{ $resource }}" :label="$this->resourceLabel($resource)" />
                    @endforeach
                </flux:checkbox.group>

                <flux:field variant="inline">
                    <flux:label>{{ __('Secret dauerhaft abrufbar machen') }}</flux:label>
                    <flux:switch wire:model="revealSecret" />
                    <flux:description>
                        {{ __('Standardmäßig wird das Secret nur einmal direkt nach dem Erstellen angezeigt. Aktiviert, kannst du es jederzeit in der Liste unten wieder einsehen.') }}
                    </flux:description>
                </flux:field>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" icon="plus">
                        {{ __('Webhook erstellen') }}
                    </flux:button>
                    <x-action-message on="webhook-created">
                        {{ __('Webhook erstellt.') }}
                    </x-action-message>
                </div>
            </form>

            <flux:separator />

            {{-- Existing subscriptions --}}
            <div>
                <flux:heading size="lg" class="mb-4">{{ __('Deine Webhooks') }}</flux:heading>

                @if ($subscriptions->isEmpty())
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Du hast noch keine Webhook-Subscription erstellt.') }}
                    </flux:text>
                @else
                    <div class="flex flex-col gap-4">
                        @foreach ($subscriptions as $subscription)
                            <div wire:key="subscription-{{ $subscription->id }}"
                                 class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">

                                @if ($editingId === $subscription->id)
                                    <form wire:submit="save" class="space-y-4">
                                        <flux:input wire:model="editUrl" type="url" :label="__('Ziel-URL')" />

                                        <flux:checkbox.group wire:model="editResources" :label="__('Ressourcen')">
                                            @foreach ($allowedResources as $resource)
                                                <flux:checkbox value="{{ $resource }}" :label="$this->resourceLabel($resource)" />
                                            @endforeach
                                        </flux:checkbox.group>

                                        <flux:field variant="inline">
                                            <flux:label>{{ __('Secret dauerhaft abrufbar machen') }}</flux:label>
                                            <flux:switch wire:model="editRevealSecret" />
                                        </flux:field>

                                        <div class="flex items-center gap-2">
                                            <flux:button variant="primary" type="submit" size="sm">
                                                {{ __('Speichern') }}
                                            </flux:button>
                                            <flux:button variant="ghost" type="button" size="sm" wire:click="cancelEdit">
                                                {{ __('Abbrechen') }}
                                            </flux:button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="truncate font-mono text-sm">{{ $subscription->url }}</p>
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ collect($subscription->resources)
                                                    ->map(fn (string $resource): string => $this->resourceLabel($resource))
                                                    ->implode(', ') }}
                                            </p>
                                        </div>

                                        {{-- Issue #98. "Awaiting approval" was amber, and a `size="sm"`
                                             flux:badge sets 12px at weight 500 — not large text, so WCAG
                                             1.4.3 wants 4.5:1. Read off the rendered pixels of this very
                                             badge (Playwright screenshot, decoded twice — GD's
                                             imagecolorat and a canvas getImageData readback in the page,
                                             agreeing to three decimals), amber measured amber-700
                                             #BB4D00 on amber-400/25 over the light page = 4.340:1. Dark
                                             mode was never the problem: #FEE685 on #7C6016 = 4.764:1.
                                             The pair is not computable from the Tailwind tokens, because
                                             the fill carries alpha and only the compositor knows the
                                             ground it lands on.

                                             Blue, measured the same way: 7.347:1 light (#193CB8 on
                                             #DCECFF), 5.234:1 dark (#BEDBFF on #36577E).

                                             Why blue and not a darker amber: this state has to stay
                                             legible NEXT TO the other three on the same page. Amber's
                                             separation from lime — "awaiting approval" versus "active",
                                             the one pair a reader must never merge — is ΔE00 0.2 in
                                             light and 0.8 in dark once a deuteranope's vision is
                                             simulated (Viénot/Brettel/Mollon 1999), i.e. the same
                                             colour. Blue's worst separation against lime, zinc and red,
                                             over normal, protanope and deuteranope vision, is ΔE00 9.1
                                             (light) / 14.5 (dark), and against lime specifically it
                                             GROWS under red-green deficiency (28.5 → 29.9/30.4) instead
                                             of collapsing. Blue is also already this portal's
                                             informational badge (dashboard/activities.blade.php:105),
                                             so no new hue enters the palette.

                                             Pinned by tests/Browser/Settings/WebhookStatusBadgeContrastTest.php,
                                             which measures the pixels again rather than asserting a class. --}}
                                        @php
                                            $status = $this->statusFor($subscription);
                                            $statusColor = match ($status) {
                                                'active' => 'lime',
                                                'paused' => 'zinc',
                                                'disabled' => 'red',
                                                'rejected' => 'red',
                                                default => 'blue',
                                            };
                                            $statusLabel = match ($status) {
                                                'active' => __('Aktiv'),
                                                'paused' => __('Pausiert'),
                                                'disabled' => __('Deaktiviert'),
                                                'rejected' => __('Abgelehnt'),
                                                default => __('Wartet auf Freigabe'),
                                            };
                                        @endphp
                                        <flux:badge size="sm" :color="$statusColor"
                                                    data-testid="webhook-status-badge"
                                                    data-status="{{ $status }}">{{ $statusLabel }}</flux:badge>
                                    </div>

                                    @if ($subscription->reveal_secret)
                                        <div class="mt-3 flex items-center gap-2" x-data="{ copied: false }">
                                            <flux:input x-ref="secret-{{ $subscription->id }}" readonly
                                                        value="{{ $subscription->secret }}" class="font-mono" size="sm" />
                                            <flux:button type="button" size="sm" icon="clipboard-document"
                                                         :aria-label="__('Secret kopieren')"
                                                         x-on:click="navigator.clipboard.writeText($refs['secret-{{ $subscription->id }}'].value); copied = true; setTimeout(() => copied = false, 2000)">
                                                <span x-show="!copied">{{ __('Kopieren') }}</span>
                                                <span x-show="copied" x-cloak>{{ __('Kopiert!') }}</span>
                                            </flux:button>
                                        </div>
                                    @endif

                                    <div class="mt-4 flex items-center gap-2">
                                        <flux:button variant="ghost" size="sm" icon="pencil"
                                                     :aria-label="__('Bearbeiten')"
                                                     wire:click="edit({{ $subscription->id }})">
                                            {{ __('Bearbeiten') }}
                                        </flux:button>

                                        @if ($status !== 'pending')
                                            <flux:button variant="ghost" size="sm"
                                                         :icon="$subscription->active ? 'pause' : 'play'"
                                                         wire:click="toggleActive({{ $subscription->id }})">
                                                {{ $subscription->active ? __('Pausieren') : __('Fortsetzen') }}
                                            </flux:button>
                                        @endif

                                        <flux:tooltip :content="__('Löschen')">
                                            <flux:button variant="danger" size="sm" icon="trash"
                                                         :aria-label="__('Webhook löschen')"
                                                         wire:click="deleteSubscription({{ $subscription->id }})"
                                                         wire:confirm="{{ __('Webhook wirklich löschen? Zustellungen an :url stoppen sofort.', ['url' => $subscription->url]) }}" />
                                        </flux:tooltip>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-settings.layout>
</section>
