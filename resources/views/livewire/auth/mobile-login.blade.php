<?php

use App\Http\Controllers\MobileAuthController;
use App\Models\LoginKey;
use App\Traits\SeoTrait;
use eza\lnurl;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    public ?string $lnurl = null;

    public ?string $qrCode = null;

    public ?string $signerCallbackUrl = null;

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
        // session — the wallet/signer callback arrives outside this session,
        // so it can't carry the redirect target itself.
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
     * Generate a fresh k1 challenge shared by both login methods: the
     * Lightning wallet signs it via LNURL-auth, the Nostr signer puts it
     * into the challenge tag of a kind-22242 event. Whichever callback
     * verifies first stores the LoginKey row that checkAuth polls for.
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

        // NIP-55 signers append the signed event JSON directly after "event=".
        $this->signerCallbackUrl = url('/api/nostr-login-callback').'?k1='.$this->k1.'&event=';

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
                <flux:heading class="text-center" size="xl">{{ __('Anmelden für die App') }}</flux:heading>

                <!-- Nostr via NIP-55 signer (e.g. Amber) -->
                <div x-data="{
                        signWithSigner() {
                            const event = {
                                kind: 22242,
                                created_at: Math.floor(Date.now() / 1000),
                                content: '',
                                tags: [['challenge', '{{ $k1 }}']],
                            };
                            window.location.href = 'nostrsigner:' + encodeURIComponent(JSON.stringify(event))
                                + '?compressionType=none&returnType=event&type=sign_event&appName=Einundzwanzig'
                                + '&callbackUrl=' + encodeURIComponent('{{ $signerCallbackUrl }}');
                        }
                     }">
                    <flux:button variant="primary"
                                 @click="signWithSigner"
                                 icon="cursor-arrow-ripple"
                                 x-bind:disabled="loginInProgress"
                                 class="w-full cursor-pointer">
                        {{ __('Log in mit Nostr (Amber)') }}
                    </flux:button>
                </div>

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

                {{-- Poll for the LoginKey row written by either callback.
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
