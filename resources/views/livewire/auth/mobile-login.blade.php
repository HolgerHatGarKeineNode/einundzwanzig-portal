<?php

use App\Http\Controllers\MobileAuthController;
use App\Jobs\FetchNostrProfileJob;
use App\Models\LoginKey;
use App\Support\NostrLogin;
use App\Traits\SeoTrait;
use eza\lnurl;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new #[Layout('components.layouts.auth')]
class extends Component {
    use SeoTrait;

    #[Locked]
    public ?string $k1 = null;

    #[Locked]
    public ?string $redirectUri = null;

    #[Locked]
    public ?string $deviceName = null;

    #[Locked]
    public ?string $nostrChallenge = null;

    public ?string $lnurl = null;

    public ?string $qrCode = null;

    public function mount(): void
    {
        $redirectUri = (string) request()->query('redirect_uri', MobileAuthController::ALLOWED_REDIRECT_URIS[0]);

        abort_unless(
            in_array($redirectUri, MobileAuthController::ALLOWED_REDIRECT_URIS, true),
            403,
            'Invalid redirect_uri',
        );

        $this->redirectUri = $redirectUri;
        $this->deviceName = str((string) request()->query('device_name', MobileAuthController::DEFAULT_DEVICE_NAME))
            ->limit(64, '')
            ->whenEmpty(fn () => str(MobileAuthController::DEFAULT_DEVICE_NAME))
            ->value();

        // The completion/confirm controller reads the flow state from the
        // session — the wallet callback arrives outside this session, so it
        // can't carry the redirect target itself.
        session([
            'mobile_auth' => [
                'redirect_uri' => $this->redirectUri,
                'device_name' => $this->deviceName,
            ],
        ]);

        if (auth()->check()) {
            return;
        }

        $this->issueNostrChallenge();
        $this->initChallenge();
    }

    /**
     * Generate a fresh k1 challenge for the LNURL flow. The Lightning
     * wallet signs it via LNURL-auth; the resulting LoginKey row is picked
     * up by checkAuth() below. The Nostr flow reuses the same row keyed by
     * the same k1 (see loginListener).
     */
    protected function initChallenge(): void
    {
        $this->k1 = bin2hex(str()->random(32));

        if (app()->environment('local')) {
            $url = 'https://mmy4dp8eab.sharedwithexpose.com/api/lnurl-auth-callback?tag=login&k1='.$this->k1.'&action=login';
        } else {
            $url = url('/api/lnurl-auth-callback?tag=login&k1='.$this->k1.'&action=login');
        }

        $this->lnurl = lnurl\encodeUrl($url);

        $image = 'public/img/domains/'.session('lang_country', 'de-DE').'.jpg';
        if (! file_exists(base_path($image))) {
            $image = 'public/img/domains/de-DE.jpg';
        }

        $this->qrCode = base64_encode(QrCode::format('png')
            ->size(300)
            ->merge('/'.$image, .3)
            ->errorCorrection('H')
            ->generate($this->lnurl));
    }

    /**
     * Session-bound challenge for the window.nostr (NIP-07/NIP-46) login —
     * identical mechanism to the regular login component.
     */
    protected function issueNostrChallenge(): string
    {
        $challenge = bin2hex(random_bytes(32));
        $this->nostrChallenge = $challenge;
        Session::put('nostr_login_challenge', $challenge);
        Session::put('nostr_login_challenge_expires_at', now()->addSeconds(NostrLogin::CHALLENGE_TTL_SECONDS)->timestamp);

        return $challenge;
    }

    public function requestNostrChallenge(): string
    {
        return $this->issueNostrChallenge();
    }

    /**
     * window.nostr signed the kind-22242 challenge (via extension or a
     * NIP-46 connection to e.g. Amber). Verify it, store the LoginKey and
     * hand the navigation to the completion route, which issues the token
     * and redirects into the app via the verified App Link.
     */
    #[On('nostrLoggedIn')]
    public function loginListener($signedEvent = null): void
    {
        $npub = $this->verifyNostrLoginEvent($signedEvent);

        $user = NostrLogin::findOrCreateUser($npub);

        FetchNostrProfileJob::dispatch($user);

        LoginKey::query()->updateOrCreate(
            ['k1' => $this->k1],
            ['user_id' => $user->id],
        );

        $this->redirect(route('auth.mobile.complete', ['k1' => $this->k1]));
    }

    protected function verifyNostrLoginEvent(mixed $signedEvent): string
    {
        $expectedChallenge = Session::get('nostr_login_challenge');
        $expiresAt = (int) Session::get('nostr_login_challenge_expires_at', 0);

        if (! is_string($expectedChallenge) || $expectedChallenge === '' || $expiresAt < now()->timestamp) {
            Session::forget(['nostr_login_challenge', 'nostr_login_challenge_expires_at']);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $npub = NostrLogin::verifyEvent($signedEvent, $expectedChallenge);

        Session::forget(['nostr_login_challenge', 'nostr_login_challenge_expires_at']);

        return $npub;
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

        // Same handoff pattern as the Lightning login: navigate via the
        // client instead of redirecting from inside wire:poll, so a stray
        // poll tick can't race the completion request.
        $this->dispatch(
            'mobile-login-ready',
            url: route('auth.mobile.complete', ['k1' => $this->k1]),
        );
    }

    public function resetAuth(): void
    {
        $this->issueNostrChallenge();
        $this->initChallenge();
    }

    public function switchAccount(): void
    {
        auth()->guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('auth.mobile', [
            'redirect_uri' => $this->redirectUri,
            'device_name' => $this->deviceName,
        ]));
    }
};
?>

<div class="flex min-h-screen justify-center"
     x-data="nostrLogin"
     data-nostr-challenge="{{ $nostrChallenge ?? '' }}"
     @mobile-login-ready.window="lightningLoginInProgress = true; window.location.href = $event.detail.url">
    <div class="flex justify-center items-center px-4 py-8">
        <div class="w-80 max-w-80 space-y-6">
            <div class="flex justify-center">
                <div class="size-24">
                    <x-app-logo-icon/>
                </div>
            </div>

            @auth
                <flux:heading class="text-center" size="xl">{{ __('Mit der App verbinden') }}</flux:heading>

                <div class="flex flex-col items-center gap-4 rounded-2xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:avatar src="{{ auth()->user()->profile_photo_url }}" size="xl"/>
                    <flux:text class="text-center">
                        {{ __('Du bist als :name angemeldet. Möchtest du dieses Gerät (:device) mit deinem Konto verbinden?', ['name' => auth()->user()->name, 'device' => $deviceName]) }}
                    </flux:text>

                    <form method="POST" action="{{ route('auth.mobile.confirm') }}" class="w-full">
                        @csrf
                        <flux:button type="submit" variant="primary" class="w-full cursor-pointer">
                            {{ __('Verbinden') }}
                        </flux:button>
                    </form>

                    <flux:button wire:click="switchAccount" variant="ghost" class="w-full cursor-pointer">
                        {{ __('Mit anderem Konto anmelden') }}
                    </flux:button>
                </div>
            @else
                <flux:heading class="text-center" size="xl">{{ __('Anmelden für die App') }}</flux:heading>

                {{-- Nostr via window.nostr: extension or NIP-46 remote signer
                     (Amber via bunker/nostrconnect — the window.nostr.js
                     widget handles pairing and persists the connection). --}}
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

                <div class="text-center text-2xl text-gray-80 dark:text-gray-2000 mt-2">
                    {{ __('Login with lightning ⚡') }}
                </div>

                <div class="flex justify-center" wire:key="qrcode">
                    <a href="lightning:{{ $this->lnurl }}">
                        <div class="bg-white p-4">
                            <img src="{{ 'data:image/png;base64, '. $this->qrCode }}" alt="qrcode">
                        </div>
                    </a>
                </div>

                <div class="flex flex-col space-y-2 justify-between w-full">
                    <flux:button
                        primary
                        href="lightning:{{ $this->lnurl }}"
                    >
                        {{ __('Mit Lightning-Wallet öffnen') }}
                    </flux:button>

                    <flux:button wire:click="resetAuth" variant="ghost" class="cursor-pointer">
                        {{ __('Neuen Code erzeugen') }}
                    </flux:button>
                </div>

                {{-- Poll for the LoginKey row written by the LNURL callback.
                     Paused while a login round-trip or navigation is in flight. --}}
                <template x-if="!nostrLoginInProgress && !lightningLoginInProgress">
                    <div wire:poll.4s="checkAuth" wire:key="checkAuth"></div>
                </template>

                <div x-show="lightningLoginInProgress" x-cloak class="flex items-center justify-center gap-2">
                    <flux:icon.arrow-path class="animate-spin size-4" aria-hidden="true"/>
                    <flux:text>{{ __('Anmeldung wird abgeschlossen…') }}</flux:text>
                </div>
            @endauth
        </div>
    </div>
</div>
