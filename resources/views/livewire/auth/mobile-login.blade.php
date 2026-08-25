<?php

use App\Http\Controllers\MobileAuthController;
use App\Models\LoginKey;
use App\Traits\SeoTrait;
use eza\lnurl;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Lightning-only login page for the mobile app.
 *
 * The app opens this page in an in-app browser for the Lightning flow.
 * The Nostr flow is driven entirely by the app itself (it launches the
 * NIP-55 signer via an ACTION_VIEW intent and the signed event is posted
 * back to /auth/mobile/signed), so there is no Nostr UI here.
 */
new #[Layout('components.layouts.auth')]
class extends Component {
    use SeoTrait;

    #[Locked]
    public ?string $k1 = null;

    #[Locked]
    public ?string $redirectUri = null;

    #[Locked]
    public ?string $deviceName = null;

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

        // The completion controller reads the flow state from the session —
        // the wallet callback arrives outside this session, so it can't
        // carry the redirect target itself.
        session([
            'mobile_auth' => [
                'redirect_uri' => $this->redirectUri,
                'device_name' => $this->deviceName,
            ],
        ]);

        if (auth()->check()) {
            return;
        }

        $this->initChallenge();
    }

    /**
     * Generate a fresh k1 challenge for the LNURL flow. The Lightning
     * wallet signs it via LNURL-auth; the resulting LoginKey row is picked
     * up by checkAuth() below.
     */
    protected function initChallenge(): void
    {
        $this->k1 = bin2hex(str()->random(32));

        if (app()->environment('local')) {
            $url = config('app.expose_url').'/api/lnurl-auth-callback?tag=login&k1='.$this->k1.'&action=login';
        } else {
            $url = url('/api/lnurl-auth-callback?tag=login&k1='.$this->k1.'&action=login');
        }

        $this->lnurl = lnurl\encodeUrl($url);

        // Dieselbe Auswahl wie Kopfbereich und Social-Media-Vorschau: eine Fassung
        // ohne eigenes Motiv bekommt TWENTY ONE, nicht mehr das deutsche Bild.
        $image = 'public/'.domain_image_path();

        $this->qrCode = base64_encode(QrCode::format('png')
            ->size(300)
            ->merge('/'.$image, .3)
            ->errorCorrection('H')
            ->generate($this->lnurl));
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

        // Same handoff pattern as the desktop Lightning login: navigate via
        // the client instead of redirecting from inside wire:poll, so a
        // stray poll tick can't race the completion request.
        $this->dispatch(
            'mobile-login-ready',
            url: route('auth.mobile.complete', ['k1' => $this->k1]),
        );
    }

    public function resetAuth(): void
    {
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
     x-data="{ loginInProgress: false }"
     @mobile-login-ready.window="loginInProgress = true; window.location.href = $event.detail.url">
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
                <flux:heading class="text-center" size="xl">{{ __('Login mit Lightning ⚡') }}</flux:heading>

                <flux:text class="text-center">
                    {{ __('Scanne den Code mit deiner Lightning-Wallet oder öffne sie direkt.') }}
                </flux:text>

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
                     Paused while the completion navigation is in flight. --}}
                <template x-if="!loginInProgress">
                    <div wire:poll.4s="checkAuth" wire:key="checkAuth"></div>
                </template>

                <div x-show="loginInProgress" x-cloak class="flex items-center justify-center gap-2">
                    <flux:icon.arrow-path class="animate-spin size-4" aria-hidden="true"/>
                    <flux:text>{{ __('Anmeldung wird abgeschlossen…') }}</flux:text>
                </div>
            @endauth
        </div>
    </div>
</div>
