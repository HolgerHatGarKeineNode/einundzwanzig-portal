<?php

use Livewire\Volt\Component;


new
#[\Livewire\Attributes\Layout('components.layouts.blank')]
class extends Component {
    public string $c = 'de';
    public string $l = 'de';

    protected array $queryString = ['c', 'l'];

    public function rules(): array
    {
        return [
            'c' => 'required',
            'l' => 'required',
        ];
    }

    public function mount(): void
    {
        $this->l = \Illuminate\Support\Facades\Cookie::get('lang') ?: config('app.locale');
        if ($this->l === 'nl-be') {
            $this->l = 'nl';
        }
        $this->c = \Illuminate\Support\Facades\Cookie::get('country') ?: config('app.country');
        \Illuminate\Support\Facades\Cookie::queue('lang', $this->l, 60 * 24 * 365);
        \Illuminate\Support\Facades\Cookie::queue('country', $this->c, 60 * 24 * 365);
    }

    public function updated(string $property, mixed $value): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $this->validate();
        $c = $property === 'c' ? $value : $this->c;
        $l = $property === 'l' ? $value : $this->l;

        if ($this->l === 'nl-be') {
            $this->l = 'nl';
        }

        \Illuminate\Support\Facades\Cookie::queue('lang', $this->l, 60 * 24 * 365);
        \Illuminate\Support\Facades\Cookie::queue('country', $this->c, 60 * 24 * 365);

        return to_route('welcome', ['c' => $c, 'l' => $l]);
    }

    public function with(): array
    {
        return [
            'countries' => \App\Models\Country::query()
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get()
                ->map(function (\App\Models\Country $country) {
                    $flag = config('countries.emoji_flags')[str($country->code)->upper()->toString()] ?? '';
                    $country->name = $flag.' '.$country->name;

                    return $country;
                }),
        ];
    }
};
?>

<div class="flex min-h-screen">
    <div class="flex-1 flex justify-center items-center p-6">
        <div class="w-80 max-w-80 space-y-6">
            <div class="flex justify-center opacity-50">
                <a href="/" class="group flex items-center gap-3">
                    <div>
                        <img src="{{ asset('img/einundzwanzig-horizontal-inverted.svg') }}" alt="Logo" class="h-6">
                    </div>
                </a>
            </div>

            <flux:heading class="text-center" size="xl">{{ __('Welcome back') }}</flux:heading>

            <div class="space-y-4">
                <flux:button class="w-full" href="{{ route('auth.login') }}">
                    {{ __('Continue with Google') }}
                </flux:button>

                <flux:button class="w-full" href="{{ route('auth.login') }}">
                    {{ __('Continue with GitHub') }}
                </flux:button>
            </div>

            <flux:separator text="{{ __('or') }}"/>

            <div class="flex flex-col gap-6">
                <flux:input label="{{ __('Email') }}" type="email" placeholder="email@example.com"/>

                <flux:field>
                    <div class="mb-3 flex justify-between">
                        <flux:label>{{ __('Password') }}</flux:label>

                        <flux:link href="{{ route('password.request') }}" variant="subtle"
                                   class="text-sm">{{ __('Forgot password?') }}</flux:link>
                    </div>

                    <flux:input type="password" placeholder="{{ __('Your password') }}"/>
                </flux:field>

                <flux:checkbox label="{{ __('Remember me for 30 days') }}"/>

                <flux:button variant="primary" class="w-full"
                             href="{{ route('auth.login') }}">{{ __('Log in') }}</flux:button>
            </div>

            <flux:subheading class="text-center">
                {{ __('First time around here?') }}
                <flux:link href="{{ route('register') }}">{{ __('Sign up for free') }}</flux:link>
            </flux:subheading>

            <div class="space-y-2">
                @feature('change.country')
                <flux:select label="{{ __('Change country') }}" wire:model.live="c">
                    @foreach ($countries as $country)
                        <flux:select.option value="{{ $country->code }}">{{ $country->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @endfeature

                @feature('change.language')
                <flux:select label="{{ __('Change language') }}" wire:model.live="l">
                    <flux:select.option value="de">Deutsch</flux:select.option>
                    <flux:select.option value="en">English</flux:select.option>
                    <flux:select.option value="nl">Nederlands</flux:select.option>
                </flux:select>
                @endfeature
            </div>
        </div>
    </div>

    <div class="flex-1 p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            @feature('meetups')
            <a href="{{ route('meetup.table.meetup', ['country' => $c]) }}"
               class="relative flex flex-col items-start justify-end w-full overflow-hidden bg-black shadow-lg rounded-xl group"
               style="aspect-ratio: 16/10;">
                <img
                    class="absolute inset-0 object-cover w-full h-full transition duration-500 lg:opacity-80 group-hover:opacity-100 group-hover:scale-110"
                    src="{{ asset('img/meetup_saarland.jpg') }}" alt="Meetups">
                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/60"></div>
                <div class="relative z-10 p-6">
                    <flux:heading size="lg" class="text-white">{{ __('Meetups') }}</flux:heading>
                    <flux:text class="text-zinc-300">{{ __('Find local Bitcoin meetups near you.') }}</flux:text>
                </div>
            </a>
            @endfeature

            @feature('events')
            <a href="{{ route('bitcoinEvent.table.bitcoinEvent', ['country' => $c]) }}"
               class="relative flex flex-col items-start justify-end w-full overflow-hidden bg-black shadow-lg rounded-xl group"
               style="aspect-ratio: 16/10;">
                <img
                    class="absolute inset-0 object-cover w-full h-full transition duration-500 lg:opacity-80 group-hover:opacity-100 group-hover:scale-110"
                    src="{{ asset('img/20220915_007_industryday.webp') }}" alt="Events">
                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/60"></div>
                <div class="relative z-10 p-6">
                    <flux:heading size="lg" class="text-white">{{ __('Events') }}</flux:heading>
                    <flux:text class="text-zinc-300">{{ __('Explore upcoming Bitcoin events worldwide.') }}</flux:text>
                </div>
            </a>
            @endfeature
        </div>
    </div>
</div>
