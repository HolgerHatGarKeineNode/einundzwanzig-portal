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
            'venues' => __('Locations'),
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
        Session::flash('status', __('Dein Nostr-Konto wurde erfolgreich verbunden. Lightning ist für dieses Konto jetzt deaktiviert — melde dich künftig mit Nostr an.'));

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
            <div class="space-y-6">
                <flux:callout variant="secondary" icon="key">
                    <flux:callout.heading>{{ __('Erkannte Nostr-Identität') }}</flux:callout.heading>
                    <flux:callout.text>
                        <code class="break-all text-xs">{{ $verifiedNpub }}</code>
                    </flux:callout.text>
                </flux:callout>

                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>
                        {{ __('Stelle sicher, dass dies genau deine npub ist. Nach der Bestätigung meldest du dich mit diesem Schlüssel an.') }}
                    </flux:callout.text>
                </flux:callout>

                {{-- Live Nostr profile (NIP-01 kind-0), loaded from relays after render. --}}
                <div wire:init="loadNostrProfile" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <p class="mb-3 text-sm font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Nostr-Profil (live)') }}</p>

                    <div wire:loading wire:target="loadNostrProfile" class="flex items-center gap-2 text-sm text-zinc-500">
                        <flux:icon.arrow-path class="animate-spin size-4" aria-hidden="true"/>
                        {{ __('Profil wird von Relays geladen…') }}
                    </div>

                    <div wire:loading.remove wire:target="loadNostrProfile">
                        @if ($nostrProfile)
                            <div class="flex items-start gap-4">
                                @if (!empty($nostrProfile['picture']))
                                    <img src="{{ $nostrProfile['picture'] }}" alt="" class="size-16 shrink-0 rounded-full object-cover"
                                         onerror="this.style.display='none'">
                                @endif
                                <div class="min-w-0 space-y-1 text-sm">
                                    @if (!empty($nostrProfile['name']))
                                        <div class="font-medium">{{ $nostrProfile['name'] }}</div>
                                    @endif
                                    @if (!empty($nostrProfile['nip05']))
                                        <div class="text-zinc-500 dark:text-zinc-400">✔ {{ $nostrProfile['nip05'] }}</div>
                                    @endif
                                    @if (!empty($nostrProfile['lud16']))
                                        <div class="text-amber-600 dark:text-amber-400">⚡ {{ $nostrProfile['lud16'] }}</div>
                                    @endif
                                    @if (!empty($nostrProfile['website']))
                                        <div class="truncate text-zinc-500 dark:text-zinc-400">{{ $nostrProfile['website'] }}</div>
                                    @endif
                                    @if (!empty($nostrProfile['about']))
                                        <p class="whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ \Illuminate\Support\Str::limit($nostrProfile['about'], 280) }}</p>
                                    @endif
                                </div>
                            </div>
                        @elseif ($nostrProfileLoaded)
                            <p class="text-sm text-zinc-500">{{ __('Kein Nostr-Profil gefunden oder Relays nicht erreichbar.') }}</p>
                        @endif
                    </div>
                </div>

                @if ($willMerge)
                    <flux:callout variant="success" icon="shield-check">
                        <flux:callout.heading>{{ __('Alle Leaderships bleiben erhalten') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Jede Meetup-Leadership und jeder Inhalt des anderen Kontos wird vollständig in dein aktuelles Konto übertragen. Erst danach wird das dann leere Konto gelöscht — du verlierst nichts.') }}
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <flux:callout variant="secondary" icon="information-circle">
                        <flux:callout.text>{{ __('Diese npub ist noch keinem Konto zugeordnet und wird einfach mit deinem aktuellen Konto verknüpft.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                @php
                    $accounts = [['title' => __('Dein aktuelles Konto (bleibt)'), 'info' => $survivorInfo, 'absorb' => false]];
                    if ($willMerge) {
                        $accounts[] = ['title' => __('Wird eingeschmolzen & gelöscht'), 'info' => $loserInfo, 'absorb' => true];
                    }
                @endphp

                <div class="grid gap-4 @if ($willMerge) md:grid-cols-2 @endif">
                    @foreach ($accounts as $account)
                        @php($info = $account['info'])
                        <div class="rounded-lg border p-4 text-sm {{ $account['absorb'] ? 'border-amber-300 dark:border-amber-700/60' : 'border-emerald-300 dark:border-emerald-700/60' }}">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="inline-block size-2 rounded-full {{ $account['absorb'] ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                                <span class="text-xs font-semibold uppercase tracking-wide {{ $account['absorb'] ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $account['title'] }}</span>
                            </div>

                            <div class="flex items-center gap-3">
                                @if (!empty($info['photo']))
                                    <img src="{{ $info['photo'] }}" alt="" class="size-10 rounded-full object-cover" onerror="this.style.display='none'">
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $info['name'] ?? '—' }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('Erstellt am') }} {{ $info['created_at'] ?? '—' }} · {{ __('Reputation') }} {{ $info['reputation'] ?? 0 }}</div>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span class="rounded px-2 py-0.5 text-xs {{ ($info['has_lightning'] ?? false) ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' }}">⚡ {{ ($info['has_lightning'] ?? false) ? __('Lightning') : __('kein Lightning') }}</span>
                                <span class="rounded px-2 py-0.5 text-xs {{ ($info['has_nostr'] ?? false) ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' }}">🟣 {{ ($info['has_nostr'] ?? false) ? __('Nostr') : __('kein Nostr') }}</span>
                                @foreach (($info['roles'] ?? []) as $role)
                                    <span class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ $role }}</span>
                                @endforeach
                            </div>

                            @if (!empty($info['lightning_address']))
                                <div class="mt-2 truncate text-xs text-zinc-500">{{ __('Lightning-Adresse') }}: {{ $info['lightning_address'] }}</div>
                            @endif

                            @if ($account['absorb'] && (!empty($info['leader_meetups']) || !empty($info['member_meetups']) || !empty($info['counts'])))
                                <div class="mt-3 flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    <flux:icon.arrow-right class="size-4 shrink-0" aria-hidden="true"/>
                                    {{ __('Alles Folgende wird in dein Konto übertragen') }}
                                </div>
                            @endif

                            @if (!empty($info['leader_meetups']))
                                <div class="mt-3">
                                    <div class="text-xs font-semibold text-zinc-500">{{ __('Leiter von') }} ({{ count($info['leader_meetups']) }})</div>
                                    <ul class="mt-1 space-y-0.5 text-zinc-700 dark:text-zinc-300">
                                        @foreach ($info['leader_meetups'] as $name)
                                            <li>👑 {{ $name }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (!empty($info['member_meetups']))
                                <div class="mt-2">
                                    <div class="text-xs font-semibold text-zinc-500">{{ __('Mitglied bei') }} ({{ count($info['member_meetups']) }})</div>
                                    <ul class="mt-1 space-y-0.5 text-zinc-600 dark:text-zinc-400">
                                        @foreach ($info['member_meetups'] as $name)
                                            <li>· {{ $name }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (!empty($info['counts']))
                                <div class="mt-3">
                                    <div class="text-xs font-semibold text-zinc-500">{{ __('Erstellte Inhalte') }}</div>
                                    <div class="mt-1 grid grid-cols-2 gap-x-3 gap-y-0.5 text-zinc-600 dark:text-zinc-400">
                                        @foreach ($info['counts'] as $label => $count)
                                            <div class="flex justify-between gap-2"><span class="truncate">{{ $label }}</span><span class="font-medium">{{ $count }}</span></div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (empty($info['leader_meetups']) && empty($info['member_meetups']) && empty($info['counts']))
                                <div class="mt-3 text-xs text-zinc-400">{{ __('Keine weiteren Verknüpfungen.') }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @include('livewire.settings.partials.profile-picker', ['fields' => $this->profileFields()])

                <flux:callout variant="warning" icon="key">
                    <flux:callout.heading>{{ __('Wichtig: Lightning wird deaktiviert') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Nach dem Verbinden kannst du dich für dieses Konto NICHT mehr per Lightning anmelden. Dein Nostr-Schlüssel ist dann der einzige Zugang. Sichere dein Schlüssel-Backup (nsec bzw. Amber) besonders gut — verlierst du ihn, verlierst du den Zugang zum Konto.') }}
                    </flux:callout.text>
                </flux:callout>

                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:checkbox wire:model.live="acknowledgedBackup"/>
                    <span class="text-sm text-zinc-700 dark:text-zinc-300">
                        {{ __('Ich habe meinen Nostr-Schlüssel sicher gesichert und verstehe, dass der Lightning-Login danach deaktiviert ist.') }}
                    </span>
                </label>
                @error('acknowledgedBackup')
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <div class="flex flex-wrap gap-3">
                    <flux:button variant="primary" class="cursor-pointer" wire:click="confirmMerge"
                                 x-bind:disabled="!$wire.acknowledgedBackup">
                        {{ __('Konten jetzt verbinden') }}
                    </flux:button>
                    <flux:button variant="ghost" class="cursor-pointer" x-on:click="window.location.reload()">
                        {{ __('Abbrechen') }}
                    </flux:button>
                </div>

                <div class="text-center">
                    <flux:link :href="$this->dashboardUrl()" wire:navigate class="text-sm">
                        {{ __('Später migrieren – zum Dashboard') }}
                    </flux:link>
                </div>
            </div>
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
