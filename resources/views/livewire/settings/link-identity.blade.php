<?php

use App\Actions\MergeUserAccounts;
use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Models\User;
use App\Support\NostrLogin;
use App\Traits\SeoTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')]
#[SeoDataAttribute(key: 'settings_link_identity')]
class extends Component {
    use SeoTrait;

    /** 'prove_nostr' | 'lightning_hint' | 'linked' */
    public string $mode = 'prove_nostr';

    public ?string $nostrChallenge = null;

    /** Set once a signature has been verified this session; drives the confirm step. */
    public bool $proofReady = false;

    public ?string $verifiedNpub = null;

    public bool $willMerge = false;

    /** Relations/profile snapshot of the current account (survivor). @var array<string, mixed> */
    public array $survivorInfo = [];

    /** Relations/profile snapshot of the account being absorbed (loser). @var array<string, mixed> */
    public array $loserInfo = [];

    /** Live-fetched Nostr kind-0 profile of the verified npub. @var array<string, mixed>|null */
    public ?array $nostrProfile = null;

    public bool $nostrProfileLoaded = false;

    /**
     * Per-field source choice for the consolidated profile: field => 'survivor'
     * | 'loser' | 'nostr'. Client-selectable, but re-validated server-side on
     * confirm (the value is always re-derived from the chosen source, never
     * trusted from the client). @var array<string, string>
     */
    public array $profileChoices = [
        'photo' => 'survivor',
        'name' => 'survivor',
        'lightning_address' => 'survivor',
    ];

    /** Must be checked before the merge runs: acknowledges Lightning is retired and the Nostr key is backed up. */
    public bool $acknowledgedBackup = false;

    private const CHALLENGE_SESSION_KEY = 'merge_nostr_challenge';

    private const CHALLENGE_EXPIRES_KEY = 'merge_nostr_challenge_expires_at';

    private const VERIFIED_NPUB_KEY = 'merge_verified_npub';

    private const NOSTR_PROFILE_KEY = 'merge_nostr_profile';

    /**
     * Human-readable intent embedded in (and required on) the signed event, so
     * the Nostr signer shows the user WHAT they are signing. A relayed empty
     * challenge from a phishing page would lack it and is rejected — this is the
     * purpose/domain binding a bare challenge otherwise lacks. Kept as a stable,
     * untranslated constant so the server-side check is locale-independent.
     */
    public const MERGE_INTENT = 'einundzwanzig.space: Nostr-Konto mit Portal-Konto verbinden';

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->nostr !== null && $user->public_key !== null) {
            $this->mode = 'linked';

            return;
        }

        if ($user->nostr !== null) {
            // Logged in via Nostr, no Lightning identity: to claim an old
            // Lightning account the user must prove that wallet — which today
            // means logging in via Lightning during the grace period.
            $this->mode = 'lightning_hint';

            return;
        }

        $this->mode = 'prove_nostr';
        $this->issueChallenge();
    }

    private function issueChallenge(): void
    {
        $challenge = bin2hex(random_bytes(32));
        $this->nostrChallenge = $challenge;
        Session::put(self::CHALLENGE_SESSION_KEY, $challenge);
        Session::put(self::CHALLENGE_EXPIRES_KEY, now()->addSeconds(NostrLogin::CHALLENGE_TTL_SECONDS)->timestamp);
    }

    /**
     * Step 1: verify the signed Nostr event. The proven npub is stored in the
     * SESSION (never a client prop) so the confirm step cannot be tricked into
     * merging a different account — that would be identity theft.
     */
    public function proveNostr(mixed $signedEvent = null): void
    {
        $expected = Session::get(self::CHALLENGE_SESSION_KEY);
        $expiresAt = (int) Session::get(self::CHALLENGE_EXPIRES_KEY, 0);

        if (! is_string($expected) || $expected === '' || $expiresAt < now()->timestamp) {
            Session::forget([self::CHALLENGE_SESSION_KEY, self::CHALLENGE_EXPIRES_KEY]);
            $this->issueChallenge();
            throw ValidationException::withMessages(['nostr' => __('Die Signatur-Anfrage ist abgelaufen. Bitte erneut versuchen.')]);
        }

        // Reject anything not signed with our explicit merge intent — a relayed
        // bare challenge (phishing) would carry a different/empty content.
        if (! is_array($signedEvent) || ($signedEvent['content'] ?? null) !== self::MERGE_INTENT) {
            throw ValidationException::withMessages(['nostr' => __('Die Signatur passt nicht zu dieser Aktion. Bitte erneut mit Nostr signieren.')]);
        }

        $npub = NostrLogin::verifyEvent($signedEvent, $expected);

        Session::forget([self::CHALLENGE_SESSION_KEY, self::CHALLENGE_EXPIRES_KEY]);

        $existing = User::query()
            ->where('nostr', $npub)
            ->where('id', '!=', Auth::id())
            ->first();

        Session::put(self::VERIFIED_NPUB_KEY, $npub);

        $this->verifiedNpub = $npub;
        $this->proofReady = true;
        $this->willMerge = $existing !== null;
        $this->survivorInfo = $this->accountInfo(Auth::user());
        $this->loserInfo = $existing ? $this->accountInfo($existing) : [];
        $this->nostrProfile = null;
        $this->nostrProfileLoaded = false;
        Session::forget(self::NOSTR_PROFILE_KEY);
        $this->applyDefaultChoices();
    }

    /**
     * Gather a display snapshot of an account: profile basics, identity, Meetups
     * by name (leader vs member) and created-entity counts per category. Read
     * from the DB — cheap and reliable; the live Nostr profile loads separately.
     *
     * @return array<string, mixed>
     */
    protected function accountInfo(User $u): array
    {
        $ledIds = Meetup::query()->ledBy($u->id)->pluck('id');
        $led = Meetup::query()->whereIn('id', $ledIds)->pluck('name')->all();
        $member = Meetup::query()
            ->associatedWith($u->id)
            ->whereNotIn('id', $ledIds)
            ->pluck('name')
            ->all();

        $categories = [
            'meetups' => __('Meetups erstellt'),
            'cities' => __('Städte'),
            'lecturers' => __('Referenten'),
            'courses' => __('Kurse'),
            'course_events' => __('Kurs-Termine'),
            'meetup_events' => __('Meetup-Termine'),
            'bitcoin_events' => __('Bitcoin-Events'),
            'podcasts' => __('Podcasts'),
            'episodes' => __('Episoden'),
            'libraries' => __('Bibliotheken'),
            'library_items' => __('Bibliotheks-Einträge'),
            'self_hosted_services' => __('Services'),
        ];

        $counts = [];
        foreach ($categories as $table => $label) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table) && \Illuminate\Support\Facades\Schema::hasColumn($table, 'created_by')) {
                $c = \Illuminate\Support\Facades\DB::table($table)->where('created_by', $u->id)->count();
                if ($c > 0) {
                    $counts[$label] = $c;
                }
            }
        }
        $votes = \Illuminate\Support\Facades\Schema::hasTable('votes')
            ? \Illuminate\Support\Facades\DB::table('votes')->where('user_id', $u->id)->count()
            : 0;
        if ($votes > 0) {
            $counts[__('Votes')] = $votes;
        }

        return [
            'id' => $u->id,
            'name' => $u->name,
            'photo' => $u->profile_photo_url,
            'has_photo' => $u->profile_photo_path !== null,
            'created_at' => optional($u->created_at)->format('d.m.Y'),
            'reputation' => (int) $u->reputation,
            'has_lightning' => (bool) ($u->public_key || $u->lightning_address || $u->lnurl),
            'lightning_address' => $u->lightning_address,
            'has_nostr' => (bool) $u->nostr,
            'roles' => $u->getRoleNames()->all(),
            'leader_meetups' => $led,
            'member_meetups' => $member,
            'counts' => $counts,
        ];
    }

    /**
     * Build the per-field profile picker: each field lists the sources that
     * actually carry a value (survivor / incoming account / live Nostr profile),
     * with a preview and a "new" flag when the survivor lacks it. Recomputed on
     * every render, so Nostr options appear once the live profile has loaded.
     *
     * @return array<string, array<string, mixed>>
     */
    public function profileFields(): array
    {
        $survivor = $this->survivorInfo;
        $loser = $this->loserInfo;
        $nostr = $this->nostrProfile;

        // Options are numerically-indexed lists (source embedded) so the Blade
        // loop stays value-only. A nested key=>value @foreach (outer $fields plus
        // inner options) miscompiles under Livewire's loop-key compiler.
        $photoOptions = [];
        // Survivor's photo is always offered (its URL falls back to an avatar).
        $photoOptions[] = ['src' => 'survivor', 'preview' => $survivor['photo'] ?? null, 'is_new' => false];
        if (! empty($loser['has_photo'])) {
            $photoOptions[] = ['src' => 'loser', 'preview' => $loser['photo'] ?? null, 'is_new' => empty($survivor['has_photo'])];
        }
        if (! empty($nostr['picture'])) {
            $photoOptions[] = ['src' => 'nostr', 'preview' => $nostr['picture'], 'is_new' => empty($survivor['has_photo'])];
        }

        $nameOptions = [];
        foreach (['survivor' => $survivor['name'] ?? null, 'loser' => $loser['name'] ?? null, 'nostr' => $nostr['name'] ?? null] as $src => $val) {
            if (! empty($val)) {
                $nameOptions[] = ['src' => $src, 'preview' => $val, 'is_new' => false];
            }
        }

        $lnOptions = [];
        foreach (['survivor' => $survivor['lightning_address'] ?? null, 'loser' => $loser['lightning_address'] ?? null, 'nostr' => $nostr['lud16'] ?? null] as $src => $val) {
            if (! empty($val)) {
                $lnOptions[] = ['src' => $src, 'preview' => $val, 'is_new' => empty($survivor['lightning_address'])];
            }
        }

        $fields = [
            'photo' => ['label' => __('Profilbild'), 'type' => 'photo', 'options' => $photoOptions],
            'name' => ['label' => __('Anzeigename'), 'type' => 'text', 'options' => $nameOptions],
        ];
        if ($lnOptions !== []) {
            $fields['lightning_address'] = ['label' => __('Lightning-Adresse'), 'type' => 'text', 'options' => $lnOptions];
        }

        return $fields;
    }

    /**
     * Default each field to the survivor where it already has a value, otherwise
     * fill the gap with the nicer data the user lacked — Nostr preferred over the
     * incoming account. Runs before any interaction (proveNostr and once the live
     * Nostr profile arrives), so recomputing is safe.
     */
    protected function applyDefaultChoices(): void
    {
        $survivor = $this->survivorInfo;
        $survivorHas = [
            'photo' => ! empty($survivor['has_photo']),
            'name' => ! empty($survivor['name']),
            'lightning_address' => ! empty($survivor['lightning_address']),
        ];

        foreach ($this->profileFields() as $field => $def) {
            $available = array_column($def['options'], 'src');

            if (($survivorHas[$field] ?? false) && in_array('survivor', $available, true)) {
                $this->profileChoices[$field] = 'survivor';

                continue;
            }

            $default = null;
            foreach (['nostr', 'loser', 'survivor'] as $src) {
                if (in_array($src, $available, true)) {
                    $default = $src;
                    break;
                }
            }
            $this->profileChoices[$field] = $default ?? ($available[0] ?? 'survivor');
        }
    }

    /**
     * Deferred (wire:init) live fetch of the verified npub's kind-0 profile from
     * relays. Kept out of proveNostr() so a slow/failing relay never blocks the
     * confirm step — the relations render instantly, the profile fills in after.
     */
    public function loadNostrProfile(): void
    {
        if ($this->nostrProfileLoaded) {
            return;
        }
        $this->nostrProfileLoaded = true;

        $npub = Session::get(self::VERIFIED_NPUB_KEY);
        if (is_string($npub) && $npub !== '') {
            $this->nostrProfile = $this->fetchNostrProfileMetadata($npub);
            // Persist server-side: confirmMerge re-derives the chosen Nostr
            // values from here, never from the client-mutable public prop.
            Session::put(self::NOSTR_PROFILE_KEY, $this->nostrProfile);
            $this->applyDefaultChoices();
        }
    }

    /**
     * Fetch a single NIP-01 kind-0 metadata event for the given npub, live from
     * relays. Returns the parsed profile (display_name, about, picture, nip05,
     * lud16, website) or null on any failure/timeout.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchNostrProfileMetadata(string $npub): ?array
    {
        try {
            $hex = (new \swentel\nostr\Key\Key)->convertToHex($npub);

            $subscription = new \swentel\nostr\Subscription\Subscription;
            $filter = new \swentel\nostr\Filter\Filter;
            $filter->setAuthors([$hex]);
            $filter->setKinds([0]);
            $filter->setLimit(1);
            $requestMessage = new \swentel\nostr\Message\RequestMessage($subscription->getId(), [$filter]);

            $relaySet = new \swentel\nostr\Relay\RelaySet;
            $relaySet->setRelays([
                new \swentel\nostr\Relay\Relay('wss://nos.lol'),
                new \swentel\nostr\Relay\Relay('wss://relay.damus.io'),
                new \swentel\nostr\Relay\Relay('wss://relay.primal.net'),
            ]);

            $response = (new \swentel\nostr\Request\Request($relaySet, $requestMessage))->send();

            foreach ($response as $relayResponses) {
                foreach ($relayResponses as $message) {
                    if (! isset($message->event->content)) {
                        continue;
                    }
                    $meta = json_decode($message->event->content, true);
                    if (is_array($meta)) {
                        return [
                            'name' => $meta['display_name'] ?? $meta['name'] ?? null,
                            'about' => $meta['about'] ?? null,
                            'picture' => $meta['picture'] ?? null,
                            'nip05' => $meta['nip05'] ?? null,
                            'lud16' => $meta['lud16'] ?? ($meta['lud06'] ?? null),
                            'website' => $meta['website'] ?? null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Merge wizard: Nostr profile fetch failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Step 2: execute the link/merge. The npub is re-read from the session and
     * the loser re-resolved server-side — the client cannot influence which
     * account is absorbed.
     */
    public function confirmMerge(MergeUserAccounts $merge): void
    {
        $user = Auth::user();
        $npub = Session::get(self::VERIFIED_NPUB_KEY);

        if (! is_string($npub) || $npub === '' || $user->nostr !== null) {
            Session::forget(self::VERIFIED_NPUB_KEY);
            throw ValidationException::withMessages(['nostr' => __('Bitte signiere zuerst erneut.')]);
        }

        if (! $this->acknowledgedBackup) {
            throw ValidationException::withMessages(['acknowledgedBackup' => __('Bitte bestätige, dass du deinen Nostr-Schlüssel gesichert hast.')]);
        }

        $loser = User::query()
            ->where('nostr', $npub)
            ->where('id', '!=', $user->id)
            ->first();

        $nostrMeta = Session::get(self::NOSTR_PROFILE_KEY);

        // Resolve the picked profile values BEFORE the merge deletes the loser.
        // Only the source key comes from the client; the value is re-derived here
        // from the authoritative model/session — never trusted from the client.
        $chosen = $this->resolveChosenProfile($user, $loser, is_array($nostrMeta) ? $nostrMeta : null);

        if ($loser === null) {
            // No separate account: just stamp the npub onto the current one.
            $user->nostr = $npub;
            $user->save();
        } else {
            $merge->handle(
                survivor: $user,
                loser: $loser,
                verifiedIdentity: $npub,
                direction: 'nostr_into_lightning',
                actorId: $user->id,
            );
        }

        // Retire the Lightning credential: LNURL-auth login is refused from here
        // on (the user is pointed at Nostr). public_key is kept so the login
        // still matches this account — otherwise a Lightning login would create a
        // fresh orphan account instead of hitting the retirement notice.
        if ($user->public_key !== null) {
            $user->lightning_retired_at = now();
        }

        $this->applyChosenProfile($user, $chosen);

        Session::forget([self::VERIFIED_NPUB_KEY, self::NOSTR_PROFILE_KEY]);

        // Nur beim echten Merge verschwinden App-Tokens: sie hingen am gelöschten
        // Konto (MergeUserAccounts::discardLoserTokens). Wurde die npub bloß
        // gestempelt, bleibt jede Anmeldung bestehen — dann kein Hinweis.
        Session::flash('status', $loser === null
            ? __('Dein Nostr-Konto wurde erfolgreich verbunden. Lightning ist für dieses Konto jetzt deaktiviert — melde dich künftig mit Nostr an.')
            : __('Dein Nostr-Konto wurde erfolgreich verbunden. Lightning ist für dieses Konto jetzt deaktiviert — melde dich künftig mit Nostr an. Die Companion-App wurde dabei abgemeldet: melde dich dort einmalig neu mit deinem Nostr-Schlüssel an.'));

        $this->redirect($this->dashboardUrl(), navigate: false);
    }

    /**
     * Map the per-field source choices to concrete values, read from the
     * authoritative sources (current user / loser model / server-fetched Nostr
     * meta). Empty picks are dropped so nothing is ever wiped.
     *
     * @param  array<string, mixed>|null  $nostrMeta
     * @return array{name: ?string, lightning_address: ?string, photo_path: ?string, photo_url: ?string}
     */
    protected function resolveChosenProfile(User $survivor, ?User $loser, ?array $nostrMeta): array
    {
        $name = match ($this->profileChoices['name'] ?? 'survivor') {
            'loser' => $loser?->name,
            'nostr' => $nostrMeta['name'] ?? null,
            default => $survivor->name,
        };

        $lightning = match ($this->profileChoices['lightning_address'] ?? 'survivor') {
            'loser' => $loser?->lightning_address,
            'nostr' => $nostrMeta['lud16'] ?? null,
            default => $survivor->lightning_address,
        };

        $photoChoice = $this->profileChoices['photo'] ?? 'survivor';
        $photoPath = null;
        $photoUrl = null;
        if ($photoChoice === 'loser' && $loser && $loser->profile_photo_path) {
            $photoPath = $loser->profile_photo_path;
        } elseif ($photoChoice === 'nostr' && ! empty($nostrMeta['picture'])) {
            $photoUrl = $nostrMeta['picture'];
        }

        return [
            'name' => is_string($name) && $name !== '' ? $name : null,
            'lightning_address' => is_string($lightning) && $lightning !== '' ? $lightning : null,
            'photo_path' => $photoPath,
            'photo_url' => $photoUrl,
        ];
    }

    /**
     * @param  array{name: ?string, lightning_address: ?string, photo_path: ?string, photo_url: ?string}  $chosen
     */
    protected function applyChosenProfile(User $user, array $chosen): void
    {
        if ($chosen['name'] !== null) {
            $user->name = $chosen['name'];
        }
        if ($chosen['lightning_address'] !== null) {
            $user->lightning_address = $chosen['lightning_address'];
        }
        if ($chosen['photo_path'] !== null) {
            $user->profile_photo_path = $chosen['photo_path'];
        } elseif ($chosen['photo_url'] !== null) {
            $path = \App\Support\NostrProfilePhoto::store($chosen['photo_url']);
            if ($path !== null) {
                $user->profile_photo_path = $path;
            }
        }

        $user->save();
    }

    public function dashboardUrl(): string
    {
        return route('dashboard', ['country' => str(session('lang_country', 'de'))->after('-')->lower()]);
    }

    public function mergeIntent(): string
    {
        return self::MERGE_INTENT;
    }
};
?>

<section class="w-full" x-data="mergeNostr"
         data-merge-challenge="{{ $nostrChallenge ?? '' }}"
         data-merge-intent="{{ $this->mergeIntent() }}">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Konten verbinden')"
                       :subheading="__('Führe Lightning und Nostr zu einem Konto zusammen — alle deine Meetup-Leaderships bleiben erhalten.')">

        <x-auth-session-status class="mb-6" :status="session('status')"/>

        @if ($mode === 'linked')
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>{{ __('Alles verbunden') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Dein Konto ist bereits mit Lightning und Nostr verknüpft. Du kannst dich mit beiden anmelden — Lightning wird künftig abgelöst.') }}
                </flux:callout.text>
            </flux:callout>
        @elseif ($mode === 'lightning_hint')
            <flux:callout variant="secondary" icon="information-circle">
                <flux:callout.heading>{{ __('Altes Lightning-Konto verbinden?') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Du bist mit Nostr angemeldet. Falls du ein älteres Lightning-Konto mit Meetups hast, melde dich einmalig per Lightning an und führe diesen Assistenten dann erneut aus — so verbindest du beide Konten sicher.') }}
                </flux:callout.text>
            </flux:callout>
        @elseif (! $proofReady)
            <div class="space-y-6">
                <flux:callout variant="warning" icon="bolt">
                    <flux:callout.heading>{{ __('Lightning-Login wird abgelöst') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Verbinde jetzt dein Nostr-Konto mit diesem Konto. Deine Meetup-Leaderships und alles, was dir gehört, bleiben vollständig erhalten. Danach meldest du dich einfach mit Nostr an.') }}
                    </flux:callout.text>
                </flux:callout>

                <div class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <p>{{ __('So funktioniert es:') }}</p>
                    <ul class="list-disc space-y-1 ps-5">
                        <li>{{ __('Du signierst eine einmalige Anfrage mit deinem Nostr-Schlüssel (Browser-Extension oder Amber).') }}</li>
                        <li>{{ __('Wir zeigen dir die erkannte npub — prüfe genau, dass es DEIN Schlüssel ist.') }}</li>
                        <li>{{ __('Erst nach deiner Bestätigung werden die Konten zusammengeführt.') }}</li>
                    </ul>
                </div>

                @error('nostr')
                    <flux:callout variant="danger" icon="x-circle">
                        <flux:callout.text>{{ $message }}</flux:callout.text>
                    </flux:callout>
                @enderror

                <flux:button variant="primary" icon="cursor-arrow-ripple" class="w-full cursor-pointer"
                             x-bind:disabled="signing" @click="signAndProve">
                    <span x-show="!signing">{{ __('Mit Nostr signieren') }}</span>
                    <span x-show="signing" x-cloak class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="animate-spin size-4" aria-hidden="true"/>
                        {{ __('Signiere…') }}
                    </span>
                </flux:button>

                <flux:button variant="ghost" :href="$this->dashboardUrl()" wire:navigate class="w-full cursor-pointer">
                    {{ __('Später migrieren – zum Dashboard') }}
                </flux:button>
            </div>
        @else
                @include('livewire.settings.partials.confirm-step', ['fields' => $this->profileFields(), 'dashboardUrl' => $this->dashboardUrl()])
        @endif
    </x-settings.layout>
</section>

@script
<script>
    Alpine.data('mergeNostr', () => ({
        signing: false,
        async signAndProve() {
            if (this.signing) return;
            this.signing = true;
            try {
                if (!window.nostr || typeof window.nostr.signEvent !== 'function') {
                    throw new Error('{{ __('Kein Nostr-Signierer gefunden. Bitte installiere eine Nostr-Browser-Extension.') }}');
                }
                const challenge = this.$root.dataset.mergeChallenge;
                const event = {
                    kind: 22242,
                    created_at: Math.floor(Date.now() / 1000),
                    tags: [['challenge', challenge]],
                    content: this.$root.dataset.mergeIntent,
                };
                const signed = await window.nostr.signEvent(event);
                // Extensions return proxy objects; a JSON round-trip yields a
                // plain, serialisable event for the server.
                const plain = JSON.parse(JSON.stringify(signed));
                await this.$wire.proveNostr(plain);
            } catch (e) {
                if (window.Flux?.toast) {
                    window.Flux.toast({ text: e.message ?? 'Signatur fehlgeschlagen', variant: 'danger' });
                }
            } finally {
                this.signing = false;
            }
        },
    }));
</script>
@endscript
