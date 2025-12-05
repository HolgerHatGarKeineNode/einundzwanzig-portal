<?php

use App\Attributes\SeoDataAttribute;
use App\Traits\SeoTrait;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('components.layouts.auth')]
#[SeoDataAttribute(key: 'welcome')]
class extends Component {
    use SeoTrait;

    public function goToMeetups(): void
    {
        $this->redirect(route('meetups.index', ['country' => str(session('lang_country', config('app.domain_country')))->after('-')->lower()]), navigate: true);
    }

    public function goToMap(): void
    {
        $this->redirect(route('meetups.map', ['country' => str(session('lang_country', config('app.domain_country')))->after('-')->lower()]), navigate: true);
    }
}; ?>

<div class="flex min-h-screen">
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
            <flux:heading class="text-center" size="xl">{{ __('Bitcoin Meetups') }}</flux:heading>

            <!-- Navigation Buttons -->
            <div class="space-y-4">
                <flux:button wire:click="goToMeetups" class="cursor-pointer w-full">
                    <x-slot name="icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </x-slot>
                    {{ __('Alle Meetups anzeigen') }}
                </flux:button>

                <flux:button wire:click="goToMap" class="cursor-pointer w-full">
                    <x-slot name="icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                    </x-slot>
                    {{ __('Kartenansicht öffnen') }}
                </flux:button>

                <flux:button :href="route('dashboard', ['country' => str(session('lang_country', config('app.domain_country')))->after('-')->lower()])" class="cursor-pointer w-full"
                             icon="arrow-right-start-on-rectangle">
                    {{ __('Login') }}
                </flux:button>
            </div>

            <!-- Language Selection Accordion -->
            <livewire:language.selector/>
        </div>
    </div>

    <!-- Right Side Panel -->
    <div class="flex-1 p-4 max-lg:hidden">
        <div class="text-white relative rounded-lg h-full w-full bg-zinc-900 flex flex-col items-start justify-end p-16"
             style="background-image: url('https://images.unsplash.com/photo-1526778548025-fa2f459cd5c1?ixlib=rb-1.2.1&auto=format&fit=crop&w=1333&q=80'); background-size: cover">
            <!-- Testimonial -->
            <div class="mb-6 italic font-base text-3xl xl:text-4xl">
                {{ __('Verbinde dich mit Bitcoinern in deiner Nähe') }}
            </div>

            <!-- Info -->
            <div class="flex gap-4">
                <div class="flex flex-col justify-center font-medium">
                    <div class="text-lg">{{ __('Bitcoin Meetups') }}</div>
                    <div class="text-zinc-300">{{ __('Finde deine lokale Community') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
