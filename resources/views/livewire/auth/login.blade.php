<?php

use App\Attributes\SeoDataAttribute;
use App\Jobs\FetchNostrProfileJob;
use App\Models\LoginKey;
use App\Support\NostrLogin;
use App\Traits\SeoTrait;
use eza\lnurl;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new #[Layout('components.layouts.auth')]
class extends Component {
    use SeoTrait;

    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public ?string $k1 = null;

    public ?string $url = null;

    public ?string $lnurl = null;

    public ?string $qrCode = null;

    public ?string $currentLangCountry = 'de-DE';

    public ?string $authError = null;

    #[Locked]
    public ?string $nostrChallenge = null;

    private const NOSTR_CHALLENGE_TTL_SECONDS = NostrLogin::CHALLENGE_TTL_SECONDS;

    /**
     * Handle authError property type conversion.
     * Ensure array values from frontend are converted to string or null.
     */
    public function updatedAuthError(mixed $value): void
    {
        if (is_array($value) && empty($value)) {
            $this->authError = null;
        }
    }

    /**
     * Generate a fresh Nostr login challenge, persist it to the session, and
     * return the value. Used both during mount() and as a JS-driven fallback
     * (see requestNostrChallenge()) when the rendered challenge is missing
     * on the client — e.g. behind an HTTP cache that stripped the snapshot.
     */
    protected function issueNostrChallenge(): string
    {
        $challenge = bin2hex(random_bytes(32));
        $this->nostrChallenge = $challenge;
        Session::put('nostr_login_challenge', $challenge);
        Session::put('nostr_login_challenge_expires_at', now()->addSeconds(self::NOSTR_CHALLENGE_TTL_SECONDS)->timestamp);

        return $challenge;
    }

    /**
     * Server-side fallback for the JS layer: returns a fresh challenge.
     * Always issues a new one so a stale rendered snapshot can be recovered
     * without forcing the user to reload the page.
     */
    public function requestNostrChallenge(): string
    {
        return $this->issueNostrChallenge();
    }

    public function mount(): void
    {
        $this->currentLangCountry = session('lang_country') ?? 'de-DE';

        $this->issueNostrChallenge();

        // Nur beim ersten Mount initialisieren
        if ($this->k1 === null) {
            $this->k1 = bin2hex(str()->random(32));
            // Bind this k1 to the browser session that started the login. The
            // completion route requires the match, so a leaked/relayed k1 (it
            // travels in a GET path) cannot log this browser in as someone else
            // (login CSRF / cross-device QR relay).
            Session::put('lightning_login_k1', $this->k1);
            if (app()->environment('local')) {
                $this->url = config('app.expose_url').'/api/lnurl-auth-callback?tag=login&k1='.$this->k1.'&action=login';
            } else {
                $this->url = url('/api/lnurl-auth-callback?tag=login&k1='.$this->k1.'&action=login');
            }
            $this->lnurl = lnurl\encodeUrl($this->url);
            $image = 'public/img/domains/'.session('lang_country', 'de-DE').'.jpg';
            $checkIfFileExists = base_path($image);
            if (!file_exists($checkIfFileExists)) {
                $image = 'public/img/domains/de-DE.jpg';
            }
            $this->qrCode = base64_encode(QrCode::format('png')
                ->size(300)
                ->merge('/'.$image, .3)
                ->errorCorrection('H')
                ->generate($this->lnurl));
        }
    }

    #[On('nostrLoggedIn')]
    public function loginListener($signedEvent = null): void
    {
        $npub = $this->verifyNostrLoginEvent($signedEvent);

        $user = NostrLogin::findOrCreateUser($npub);

        FetchNostrProfileJob::dispatch($user);
        // Auth::loginUsingId() already regenerates the session id (see
        // SessionGuard::updateSession), so an explicit Session::regenerate()
        // would just rotate the CSRF token a second time. We also avoid
        // wire:navigate here: it preserves the <meta name="csrf-token"> tag
        // from the previous page, so any subsequent Livewire action on the
        // destination would 419 (TokenMismatch). A full-page redirect gives
        // the browser a fresh document with a fresh token.
        Auth::loginUsingId($user->id);

        $this->redirectIntended(
            default: route('dashboard',
                ['country' => str(session('lang_country', config('app.domain_country')))->after('-')->lower()],
                absolute: false),
        );
    }

    /**
     * Verify a NIP-42-style signed login event and return the user's npub.
     *
     * Throws ValidationException on any invalid input — never trust client data.
     */
    protected function verifyNostrLoginEvent(mixed $signedEvent): string
    {
        $expectedChallenge = Session::get('nostr_login_challenge');
        $expiresAt = (int) Session::get('nostr_login_challenge_expires_at', 0);

        if (!is_string($expectedChallenge) || $expectedChallenge === '' || $expiresAt < now()->timestamp) {
            Session::forget(['nostr_login_challenge', 'nostr_login_challenge_expires_at']);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $npub = NostrLogin::verifyEvent($signedEvent, $expectedChallenge);

        Session::forget(['nostr_login_challenge', 'nostr_login_challenge_expires_at']);

        return $npub;
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function checkAuth(): void
    {
        $loginKey = LoginKey::query()
            ->where('k1', $this->k1)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if (! $loginKey) {
            return;
        }

        // Persist the locale choice before the auth round-trip — once we
        // navigate, this component is unmounted and $currentLangCountry
        // would otherwise be lost.
        session(['lang_country' => $this->currentLangCountry]);

        // Hand the full-page navigation off to the client: returning a
        // Livewire redirect from inside wire:poll has shown races with
        // subsequent poll ticks (visible as a "request loop without
        // redirect" for the user). Dispatching an event lets Alpine pause
        // wire:poll via lightningLoginInProgress and run a clean
        // window.location navigation. The dedicated /auth/complete-lightning
        // controller then performs auth()->login() on a non-Livewire
        // request, avoiding the session-id/CSRF rotation race that
        // previously yielded 419s.
        $this->dispatch(
            'lightning-login-ready',
            url: route('auth.ln.complete', ['k1' => $this->k1]),
        );
    }

    /**
     * Get the current authentication error state.
     */
    public function getAuthError(): ?string
    {
        return $this->authError;
    }

    /**
     * Reset authentication by generating a new k1 challenge.
     */
    public function resetAuth(): void
    {
        $this->k1 = null;
        $this->url = null;
        $this->lnurl = null;
        $this->qrCode = null;
        $this->authError = null;
        $this->mount();
    }
};
?>

<div class="flex min-h-screen" x-data="nostrLogin"
     x-init="initErrorPolling"
     x-effect="document.body.style.overflow = (nostrLoginInProgress || lightningLoginInProgress) ? 'hidden' : ''"
     @lightning-login-ready.window="lightningLoginInProgress = true; window.location.href = $event.detail.url"
     data-nostr-challenge="{{ $nostrChallenge ?? '' }}">
    <div class="flex-1 flex justify-center items-center">
        <div class="w-80 max-w-80 space-y-6">
            <div class="flex justify-center">
                <a href="/" class="group flex items-center gap-3">
                    <div class="h-24 m-12 [:where(&)]:size-32 [:where(&)]:text-base">
                        <x-app-logo-icon/>
                    </div>
                </a>
            </div>

            <flux:heading class="text-center" size="xl">{{ __('Willkommen zurück') }}</flux:heading>

            <x-auth-session-status class="text-center" :status="session('status')"/>

            <div class="flex flex-col gap-6">

                <flux:button variant="primary"
                             @click="openNostrLogin"
                             icon="cursor-arrow-ripple"
                             x-bind:disabled="nostrLoginInProgress"
                             x-bind:aria-busy="nostrLoginInProgress"
                             class="w-full cursor-pointer">
                    <span x-show="!nostrLoginInProgress">{{ __('Log in mit Nostr') }}</span>
                    <span x-show="nostrLoginInProgress" x-cloak class="inline-flex items-center gap-2">
                        <flux:icon.arrow-path class="animate-spin size-4" aria-hidden="true"/>
                        {{ __('Signiere…') }}
                    </span>
                </flux:button>

                <flux:callout variant="warning" icon="bolt" class="text-start">
                    <flux:callout.heading>{{ __('Lightning-Login wird abgelöst') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Melde dich hier noch einmal per Lightning an – danach führen wir dich automatisch durch die Verbindung mit Nostr. Deine Meetup-Leaderships bleiben dabei erhalten.') }}
                    </flux:callout.text>
                </flux:callout>

                <flux:accordion>
                    <flux:accordion.item :heading="__('Lightning-Login anzeigen')">
                        <div class="flex flex-col gap-4 pt-2">
                            <div class="flex justify-center" wire:key="qrcode">
                                <a href="lightning:{{ $this->lnurl }}">
                                    <div class="bg-white p-4">
                                        <img src="{{ 'data:image/png;base64, '. $this->qrCode }}" alt="qrcode">
                                    </div>
                                </a>
                            </div>

                            <div class="flex flex-col space-y-2 justify-between w-full">
                                <div x-copy-to-clipboard="'{{ $this->lnurl }}'">
                                    <flux:button icon="clipboard" class="cursor-pointer">
                                        {{ __('Copy') }}
                                    </flux:button>
                                </div>
                                <div>
                                    <flux:button
                                        primary
                                        href="lightning:{{ $this->lnurl }}"
                                    >
                                        {{ __('Click to connect') }}
                                        <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512"
                                             height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                            <path fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"
                                                  d="M461.81 53.81a4.4 4.4 0 00-3.3-3.39c-54.38-13.3-180 34.09-248.13 102.17a294.9 294.9 0 00-33.09 39.08c-21-1.9-42-.3-59.88 7.5-50.49 22.2-65.18 80.18-69.28 105.07a9 9 0 009.8 10.4l81.07-8.9a180.29 180.29 0 001.1 18.3 18.15 18.15 0 005.3 11.09l31.39 31.39a18.15 18.15 0 0011.1 5.3 179.91 179.91 0 0018.19 1.1l-8.89 81a9 9 0 0010.39 9.79c24.9-4 83-18.69 105.07-69.17 7.8-17.9 9.4-38.79 7.6-59.69a293.91 293.91 0 0039.19-33.09c68.38-68 115.47-190.86 102.37-247.95zM298.66 213.67a42.7 42.7 0 1160.38 0 42.65 42.65 0 01-60.38 0z"></path>
                                            <path fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="32"
                                                  d="M109.64 352a45.06 45.06 0 00-26.35 12.84C65.67 382.52 64 448 64 448s65.52-1.67 83.15-19.31A44.73 44.73 0 00160 402.32"></path>
                                        </svg>
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </flux:accordion.item>
                </flux:accordion>
            </div>

            <livewire:language.selector/>
        </div>
    </div>

    <div class="flex-1 p-4 max-lg:hidden">
        <div class="text-white relative rounded-lg h-full w-full bg-zinc-900 flex flex-col items-start justify-end p-16"
             style="background-image: url('https://dergigi.com/assets/images/bitcoin-is-time.jpg'); background-size: cover">

            <div class="mb-6 italic font-base text-3xl xl:text-4xl">
                Bitcoin, not blockchain. Bitcoin, not crypto.
            </div>

            <div class="flex gap-4">
                <flux:avatar src="https://dergigi.com/assets/images/avatar.jpg" size="xl"/>
                <div class="flex flex-col justify-center font-medium">
                    <div class="text-lg">Gigi</div>
                    <div class="text-zinc-300">bitcoiner and software engineer</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pause Livewire polling while a Nostr signature round-trip is in
         flight. Otherwise wire:poll can fire a parallel /livewire/update
         request that races with auth()->login()'s session migration and
         lands on an invalidated session id, producing 419 TokenMismatch. --}}
    {{-- Pause Livewire polling while either login flow is mid-flight.
         Otherwise wire:poll can fire a parallel /livewire/update request
         that races with the navigation handled in nostrLogin.js for Nostr
         or the controller-driven Lightning flow, yielding the "request loop
         without redirect" symptom seen in production. --}}
    <template x-if="!nostrLoginInProgress && !lightningLoginInProgress">
        <div wire:poll.4s="checkAuth" wire:key="checkAuth"></div>
    </template>

    {{-- Full-viewport progress overlay. Visible while the wallet-signing
         round-trip is running. Locks input by capturing pointer events and
         intercepting Escape/Tab so the user cannot interact with anything
         underneath until the redirect resolves (or the flow errors out). --}}
    <div x-show="nostrLoginInProgress"
         x-cloak
         x-transition.opacity.duration.150ms
         role="dialog"
         aria-modal="true"
         x-bind:aria-busy="nostrLoginInProgress"
         aria-labelledby="nostr-login-progress-heading"
         aria-describedby="nostr-login-progress-description"
         @keydown.window.escape.prevent.stop
         @keydown.window.tab.prevent.stop
         class="fixed inset-0 z-[100] flex items-center justify-center bg-zinc-950/70 backdrop-blur-md">
        <div class="mx-4 w-full max-w-md rounded-2xl bg-white px-8 py-10 text-center shadow-2xl ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="relative mx-auto flex size-20 items-center justify-center">
                <span class="absolute inset-0 animate-ping rounded-full bg-amber-500/20" aria-hidden="true"></span>
                <span class="absolute inset-2 rounded-full bg-amber-500/10" aria-hidden="true"></span>
                <flux:icon.arrow-path class="relative size-10 animate-spin text-amber-600 dark:text-amber-400"
                                      aria-hidden="true"/>
            </div>

            <flux:heading id="nostr-login-progress-heading" size="lg" class="mt-6">
                {{ __('Signiere mit deinem Nostr-Wallet') }}
            </flux:heading>

            <flux:text id="nostr-login-progress-description" class="mt-3 text-zinc-600 dark:text-zinc-400">
                {{ __('Bitte bestätige die Login-Anfrage in deiner Browser-Extension. Du wirst gleich automatisch weitergeleitet.') }}
            </flux:text>

            <flux:text size="sm" class="mt-6 text-zinc-500 dark:text-zinc-500">
                {{ __('Schließe dieses Fenster nicht.') }}
            </flux:text>
        </div>
    </div>
</div>
