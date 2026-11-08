<?php

use App\Attributes\SeoDataAttribute;
use App\Models\User;
use App\Traits\SeoTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

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

    #[Computed]
    public function npub(): ?string
    {
        return Auth::user()->npub();
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
                Rule::unique(User::class)->ignore($user->id),
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
            $this->redirectIntended(default: route('dashboard', ['country' => str(session('lang_country', config('app.domain_country')))->after('-')->lower()], absolute: false));

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

        <div class="mb-6">
            <flux:heading size="lg" class="mb-4">{{ __('Dein Nostr-Schlüssel') }}</flux:heading>
            @if ($this->npub)
                <flux:subheading class="mb-4">{{ __('Dein öffentlicher Schlüssel (npub) — gefahrlos teilbar, zum Beispiel wenn dich jemand als Leader eines Meetups einsetzen möchte.') }}</flux:subheading>
                <div class="flex items-start gap-2">
                    <code x-copy-to-clipboard="'{{ $this->npub }}'" data-testid="profile-npub"
                          class="cursor-pointer block min-w-0 flex-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2.5 font-mono text-sm wrap-anywhere dark:border-white/10 dark:bg-white/5">{{ $this->npub }}</code>
                    <flux:button x-copy-to-clipboard="'{{ $this->npub }}'" icon="clipboard-document" class="shrink-0 cursor-pointer"
                                 :aria-label="__('npub kopieren')"/>
                </div>
            @else
                <flux:subheading class="mb-4">{{ __('Mit deinem Konto ist noch kein Nostr-Schlüssel verbunden.') }}</flux:subheading>
                <flux:button :href="route('settings.link-identity', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])" wire:navigate icon="key">{{ __('Nostr-Schlüssel verbinden') }}</flux:button>
            @endif
        </div>

        <div>
            <flux:heading size="lg" class="mb-4">{{ __('Zeitzone') }}</flux:heading>
            <flux:subheading class="mb-6">{{ __('Wähle deine Zeitzone aus...') }}</flux:subheading>
            <livewire:timezone.chooser :withRedirect="false"/>
        </div>

        <x-einundzwanzig.language-selector :collapsable="false"/>

        <livewire:settings.delete-user-form/>
    </x-settings.layout>
</section>
