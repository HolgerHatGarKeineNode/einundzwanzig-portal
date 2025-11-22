<?php

use App\Attributes\SeoDataAttribute;
use App\Models\User;
use App\Traits\SeoTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'settings_profile')]
class extends Component {
    use SeoTrait;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id)
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', ['country' => 'de'],absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name"/>

            {{--<div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>--}}

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <div class="my-8">
            <flux:heading size="lg" class="mb-4">{{ __('Spracheinstellungen') }}</flux:heading>
            <flux:subheading class="mb-6">{{ __('Wähle deine Sprache aus...') }}</flux:subheading>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @php
                    $languages = [
                        'de' => ['name' => 'Deutsch', 'countries' => ['de-DE', 'de-AT', 'de-CH']],
                        'en' => ['name' => 'English', 'countries' => ['en-GB', 'en-US', 'en-AU', 'en-CA']],
                        'es' => ['name' => 'Español', 'countries' => ['es-ES', 'es-CL', 'es-CO']],
                    ];
                    $currentLangCountry = session('lang_country', config('lang-country.fallback'));
                @endphp

                @foreach($languages as $langCode => $langData)
                    @foreach($langData['countries'] as $langCountry)
                        @php
                            [$lang, $countryCode] = explode('-', $langCountry);
                            $isActive = $currentLangCountry === $langCountry;
                        @endphp
                        <a href="{{ route('lang_country.switch', ['lang_country' => $langCountry]) }}"
                           class="flex flex-col items-center justify-center p-4 border rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors {{ $isActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <img
                                alt="{{ strtolower($countryCode) }}"
                                src="{{ asset('vendor/blade-flags/country-'.strtolower($countryCode).'.svg') }}"
                                class="w-12 h-8 mb-2 object-cover"
                            />
                            <span class="text-sm font-medium">{{ $langData['name'] }}</span>
                            <span class="text-xs text-zinc-500">{{ strtoupper($countryCode) }}</span>
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>

        <livewire:settings.delete-user-form/>
    </x-settings.layout>
</section>
