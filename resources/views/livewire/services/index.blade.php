<?php

use App\Attributes\SeoDataAttribute;
use App\Models\SelfHostedService;
use App\Traits\SeoTrait;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[SeoDataAttribute(key: 'services_index')]
class extends Component {
    use WithPagination;
    use SeoTrait;

    public string $country = 'de';
    public string $search = '';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function with(): array
    {
        return [
            'services' => SelfHostedService::query()
                ->when($this->search, fn($q) => $q->where('name', 'ilike', '%'.$this->search.'%'))
                ->orderBy('name')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between flex-col md:flex-row mb-6">
        <flux:heading size="xl">{{ __('Self Hosted Services') }}</flux:heading>
        <div class="flex flex-col md:flex-row items-center gap-4">
            <flux:input wire:model.live="search" :placeholder="__('Suche nach Services...')" clearable />
            @auth
                <flux:button class="cursor-pointer" :href="route_with_country('services.create')" icon="plus" variant="primary">
                    {{ __('Service erstellen') }}
                </flux:button>
            @endauth
        </div>
    </div>

    <flux:table :paginate="$services" class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Typ') }}</flux:table.column>
            <flux:table.column>{{ __('Links') }}</flux:table.column>
            <flux:table.column>{{ __('Aktionen') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($services as $service)
                <flux:table.row :key="$service->id">
                    <flux:table.cell variant="strong" class="flex items-center gap-3">
                        <flux:avatar class="[:where(&)]:size-16" :href="route('services.landingpage', ['service' => $service, 'country' => $country])" src="{{ $service->getFirstMedia('logo') ? $service->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"/>
                        <a href="{{ route('services.landingpage', ['service' => $service, 'country' => $country]) }}">{{ $service->name }}</a>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($service->type)
                            <flux:badge size="sm">{{ ucfirst($service->type->value) }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex gap-2">
                            @if($service->url_clearnet)
                                <flux:link :href="$service->url_clearnet" external variant="subtle" title="Clearnet">
                                    <flux:icon.globe-alt variant="mini" />
                                </flux:link>
                            @endif
                            @if($service->url_onion)
                                <flux:link :href="$service->url_onion" external variant="subtle" title="Onion">
                                    <flux:icon.lock-closed variant="mini" />
                                </flux:link>
                            @endif
                            @if($service->url_i2p)
                                <flux:link :href="$service->url_i2p" external variant="subtle" title="I2P">
                                    <flux:icon.link variant="mini" />
                                </flux:link>
                            @endif
                            @if($service->url_pkdns)
                                <flux:link :href="$service->url_pkdns" external variant="subtle" title="pkdns">
                                    <flux:icon.link variant="mini" />
                                </flux:link>
                            @endif
                            @if($service->contact_url)
                                <flux:link :href="$service->contact_url" external variant="subtle" title="Kontakt">
                                    <flux:icon.envelope variant="mini" />
                                </flux:link>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @auth
                            @if(auth()->id() === $service->created_by)
                                <flux:button :href="route_with_country('services.edit', ['service' => $service])" size="xs" variant="filled" icon="pencil">
                                    {{ __('Bearbeiten') }}
                                </flux:button>
                            @endif
                        @else
                            <flux:link :href="route('login')">{{ __('Log in') }}</flux:link>
                        @endauth
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
