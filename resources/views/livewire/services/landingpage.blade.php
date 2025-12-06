<?php

use App\Attributes\SeoDataAttribute;
use App\Models\SelfHostedService;
use App\Traits\SeoTrait;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'services_landingpage')]
class extends Component {
    use SeoTrait;

    public SelfHostedService $service;

    public $country = 'de';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function with(): array
    {
        return [
            'service' => $this->service,
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <div class="flex items-center gap-4">
                <flux:avatar class="[:where(&)]:size-24 [:where(&)]:text-base" size="xl"
                             src="{{ $service->getFirstMediaUrl('logo') }}"/>
                <div>
                    <flux:heading size="xl" class="mb-1">{{ $service->name }}</flux:heading>
                    @if($service->type)
                        <flux:badge size="sm">{{ ucfirst($service->type->value) }}</flux:badge>
                    @endif
                </div>
            </div>

            @if($service->intro)
                <div>
                    <flux:heading size="lg" class="mb-2">{{ __('Über den Service') }}</flux:heading>
                    <x-markdown class="prose whitespace-pre-wrap">{!! $service->intro !!}</x-markdown>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Links') }}</flux:heading>
            <div class="grid grid-cols-1 gap-2">
                @if($service->url_clearnet)
                    <flux:button href="{{ $service->url_clearnet }}" target="_blank" variant="ghost" class="justify-start">
                        <flux:icon.globe-alt class="w-5 h-5 mr-2" />
                        Clearnet
                    </flux:button>
                @endif
                @if($service->url_onion)
                    <flux:button href="{{ $service->url_onion }}" target="_blank" variant="ghost" class="justify-start">
                        <flux:icon.lock-closed class="w-5 h-5 mr-2" />
                        Onion / Tor
                    </flux:button>
                @endif
                @if($service->url_i2p)
                    <flux:button href="{{ $service->url_i2p }}" target="_blank" variant="ghost" class="justify-start">
                        <flux:icon.link class="w-5 h-5 mr-2" />
                        I2P
                    </flux:button>
                @endif
                @if($service->url_pkdns)
                    <flux:button href="{{ $service->url_pkdns }}" target="_blank" variant="ghost" class="justify-start">
                        <flux:icon.link class="w-5 h-5 mr-2" />
                        pkdns
                    </flux:button>
                @endif
                @if($service->contact_url)
                    <flux:button href="{{ $service->contact_url }}" target="_blank" variant="ghost" class="justify-start">
                        <flux:icon.envelope class="w-5 h-5 mr-2" />
                        {{ __('Kontakt') }}
                    </flux:button>
                @endif
            </div>

            @auth
                @if(auth()->id() === $service->created_by)
                    <flux:button :href="route_with_country('services.edit', ['service' => $service])" variant="primary" icon="pencil">
                        {{ __('Bearbeiten') }}
                    </flux:button>
                @endif
            @endauth
        </div>
    </div>
</div>
