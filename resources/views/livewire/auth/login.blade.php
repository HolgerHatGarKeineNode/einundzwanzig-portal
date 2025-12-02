<?php

use App\Attributes\SeoDataAttribute;
use App\Jobs\FetchNostrProfileJob;
use App\Models\LoginKey;
use App\Models\User;
use App\Notifications\ModelCreatedNotification;
use App\Traits\SeoTrait;
use eza\lnurl;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

new
#[Layout('components.layouts.auth')]
#[SeoDataAttribute(key: 'login')]
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
    public string $currentLangCountry = 'de-DE';

    public function mount(): void
    {
        $this->currentLangCountry = session('lang_country');

        // Nur beim ersten Mount initialisieren
        if ($this->k1 === null) {
            $this->k1 = bin2hex(str()->random(32));
            if (app()->environment('local')) {
                $this->url = 'https://mmy4dp8eab.sharedwithexpose.com/api/lnurl-auth-callback?tag=login&k1='.$this->k1.'&action=login';
            } else {
                $this->url = url('/api/lnurl-auth-callback?tag=login&k1='.$this->k1.'&action=login');
            }
            $this->lnurl = lnurl\encodeUrl($this->url);
            $this->qrCode = base64_encode(QrCode::format('png')
                ->size(300)
                ->merge('/public/android-chrome-192x192.png', .3)
                ->errorCorrection('H')
                ->generate($this->lnurl));
        }
    }

    #[On('nostrLoggedIn')]
    public function loginListener($pubkey): void
    {
        $user = \App\Models\User::query()->where('nostr', $pubkey)->first();
        if (!$user) {
            $fakeName = str()->random(10);
            // create User
            $user = User::create([
                'public_key' => null,
                'is_lecturer' => true,
                'name' => $fakeName,
                'email' => str($pubkey)->substr(-12).'@portal.einundzwanzig.space',
                'nostr' => $pubkey,
                'lnbits' => [
                    'read_key' => null,
                    'url' => null,
                    'wallet_id' => null,
                ],
            ]);
        }
        FetchNostrProfileJob::dispatch($user);
        Auth::loginUsingId($user->id);
        Session::regenerate();
        $this->redirectIntended(
            default: route('dashboard',
                ['country' => str(session('lang_country', config('app.domain_country')))->after('-')->lower()],
                absolute: false),
            navigate: true,
        );
        return;

        $this->validate();

        $this->ensureIsNotRateLimited();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();
        session([
            'lang_country' => $this->currentLangCountry,
        ]);

        $this->redirectIntended(
            default: route('dashboard',
                ['country' => str(session('lang_country', config('app.domain_country')))->after('-')->lower()],
                absolute: false),
            navigate: true,
        );
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

    public function checkAuth()
    {
        $loginKey = LoginKey::query()
            ->where('k1', $this->k1)
            ->whereDate('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($loginKey) {
            $user = User::find($loginKey->user_id);

            \App\Models\User::find(1)
                ->notify(new ModelCreatedNotification($user, 'users'));
            auth()->login($user);
            Session::regenerate();
            session([
                'lang_country' => $this->currentLangCountry,
            ]);

            return to_route('dashboard',
                ['country' => str(session('lang_country', config('app.domain_country')))->after('-')->lower()]);
        }

        return true;
    }
}; ?>

<div class="flex min-h-screen" x-data="nostrLogin">
    <div class="flex-1 flex justify-center items-center">
        <div class="w-80 max-w-80 space-y-6">
            <!-- Logo -->
            <div class="flex justify-center">
                <a href="/" class="group flex items-center gap-3">
                    <div class="h-24 m-12 [:where(&)]:size-32 [:where(&)]:text-base">
                        <x-app-logo-icon/>
                    </div>
                </a>
            </div>

            <!-- Welcome Heading -->
            <flux:heading class="text-center" size="xl">{{ __('Willkommen zurück') }}</flux:heading>

            <!-- Session Status -->
            <x-auth-session-status class="text-center" :status="session('status')"/>

            <!-- Login Form -->
            <div class="flex flex-col gap-6">

                <!-- Submit Button -->
                <flux:button variant="primary" @click="openNostrLogin" icon="cursor-arrow-ripple"
                             class="w-full cursor-pointer">{{ __('Log in mit Nostr') }}</flux:button>

                <div class="text-center text-2xl text-gray-80 dark:text-gray-2000 mt-6">
                    {{ __('Login with lightning ⚡') }}
                </div>

                <div class="flex justify-center" wire:key="qrcode">
                    <a href="lightning:{{ $this->lnurl }}">
                        <div class="bg-white p-4">
                            <img src="{{ 'data:image/png;base64, '. $this->qrCode }}" alt="qrcode">
                        </div>
                    </a>
                </div>

                <div class="flex justify-between w-full">
                    <div x-copy-to-clipboard="'{{ $this->lnurl }}'">
                        <flux:button icon="clipboard" class="cursor-pointer">
                            {{ __('Copy') }}
                        </flux:button>
                    </div>
                    <div>
                        <flux:button
                            primary
                            :href="'lightning:'.$this->lnurl"
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

            <!-- Sign up Link -->
            {{--@if (Route::has('register'))
                <flux:subheading class="text-center">
                    First time around here? <flux:link href="{{ route('register') }}" wire:navigate>Sign up for free</flux:link>
                </flux:subheading>
            @endif--}}
            <!-- Language Selection Accordion -->
            <x-einundzwanzig.language-selector/>
        </div>
    </div>

    <!-- Right Side Panel -->
    <div class="flex-1 p-4 max-lg:hidden">
        <div class="text-white relative rounded-lg h-full w-full bg-zinc-900 flex flex-col items-start justify-end p-16"
             style="background-image: url('https://dergigi.com/assets/images/bitcoin-is-time.jpg'); background-size: cover">

            <!-- Testimonial -->
            <div class="mb-6 italic font-base text-3xl xl:text-4xl">
                Bitcoin, not blockchain. Bitcoin, not crypto.
            </div>

            <!-- Author Info -->
            <div class="flex gap-4">
                <flux:avatar src="https://dergigi.com/assets/images/avatar.jpg" size="xl"/>
                <div class="flex flex-col justify-center font-medium">
                    <div class="text-lg">Gigi</div>
                    <div class="text-zinc-300">bitcoiner and software engineer</div>
                </div>
            </div>
        </div>
    </div>

    <div wire:poll.4s="checkAuth" wire:key="checkAuth"></div>
</div>
