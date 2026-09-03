<?php

use App\Attributes\SeoDataAttribute;
use App\Models\SelfHostedService;
use App\Traits\SeoTrait;
use Livewire\Component;
use Livewire\WithPagination;

new
#[SeoDataAttribute(key: 'services_index')]
class extends Component {
    use WithPagination;
    use SeoTrait;

    public string $country = 'de';
    public string $search = '';
    public ?string $typeFilter = null;

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function filterByType(?string $type): void
    {
        $this->typeFilter = $this->typeFilter === $type ? null : $type;
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'services' => SelfHostedService::query()
                ->with('createdBy')
                ->when($this->search, fn($q) => $q->whereLike('name', '%'.$this->search.'%'))
                ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
                ->orderBy('name')
                ->paginate(15),
            'types' => \App\Enums\SelfHostedServiceType::cases(),
        ];
    }
}; ?>

<div>
    <x-service-disclaimer/>

    <div class="flex items-center justify-between flex-col md:flex-row mb-6">
        <flux:heading size="xl">{{ __('Self Hosted Services') }}</flux:heading>
        <div class="flex flex-col md:flex-row items-center gap-4">
            <flux:input wire:model.live="search" :placeholder="__('Suche nach Services...')" clearable/>
            @auth
                <flux:button class="cursor-pointer" :href="route_with_country('services.create')" icon="plus"
                             variant="primary">
                    {{ __('Service erstellen') }}
                </flux:button>
            @endauth
        </div>
    </div>

    <!-- Type Filter Cloud -->
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach($types as $type)
            <flux:badge
                wire:click="filterByType('{{ $type->value }}')"
                size="lg"
                color="{{ $type->color() }}"
                class="cursor-pointer transition-opacity {{ $typeFilter === $type->value ? 'ring-2 ring-offset-2' : 'opacity-70 hover:opacity-100' }}"
            >
                {{ $type->label() }}
            </flux:badge>
        @endforeach
        @if($typeFilter)
            <flux:badge
                wire:click="filterByType(null)"
                size="lg"
                color="zinc"
                class="cursor-pointer"
            >
                <flux:icon.x-mark variant="mini" class="inline"/>
                {{ __('Filter zurücksetzen') }}
            </flux:badge>
        @endif
    </div>

    <flux:table :paginate="$services" class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Typ') }}</flux:table.column>
            <flux:table.column>{{ __('Links') }}</flux:table.column>
            <flux:table.column>{{ __('Erstellt von') }}</flux:table.column>
            <flux:table.column>{{ __('Datum') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($services as $service)
                <flux:table.row :key="$service->id">
                    <flux:table.cell variant="strong">
                        <a href="{{ route('services.landingpage', ['service' => $service, 'country' => $country]) }}">{{ $service->name }}</a>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($service->type)
                            <flux:badge size="sm" color="{{ $service->type->color() }}">
                                {{ $service->type->label() }}
                            </flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @php
                            $serviceUrls = [
                                ['href' => $service->url_clearnet, 'color' => 'text-blue-600 dark:text-blue-400', 'icon' => 'globe-alt', 'label' => 'Clearnet'],
                                ['href' => $service->url_onion, 'color' => 'text-purple-600 dark:text-purple-400', 'icon' => 'lock-closed', 'label' => 'Onion'],
                                ['href' => $service->url_i2p, 'color' => 'text-green-600 dark:text-green-400', 'icon' => 'link', 'label' => 'I2P'],
                                ['href' => $service->url_pkdns, 'color' => 'text-orange-600 dark:text-orange-400', 'icon' => 'link', 'label' => 'pkdns'],
                            ];
                        @endphp
                        <div class="flex flex-col gap-1">
                            @foreach($serviceUrls as $serviceUrl)
                                @if($serviceUrl['href'])
                                    <div class="flex items-center gap-2">
                                        <flux:tooltip content="{{ $serviceUrl['href'] }}">
                                            <flux:link :href="$serviceUrl['href']" external
                                                       class="{{ $serviceUrl['color'] }}">
                                                <flux:icon name="{{ $serviceUrl['icon'] }}" variant="mini" class="inline"/>
                                                {{ $serviceUrl['label'] }}
                                            </flux:link>
                                        </flux:tooltip>
                                        <div x-copy-to-clipboard="'{{ $serviceUrl['href'] }}'">
                                            <flux:button icon="clipboard" size="xs" variant="ghost" class="cursor-pointer">
                                                {{ __('Copy') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                            @if($service->ip)
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm text-gray-700 dark:text-gray-300">
                                        <flux:icon.server variant="mini" class="inline"/>
                                        {{ $service->ip }}
                                    </span>
                                    <div x-copy-to-clipboard="'{{ $service->ip }}'">
                                        <flux:button icon="clipboard" size="xs" variant="ghost" class="cursor-pointer">
                                            {{ __('Copy') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($service->anon || !$service->createdBy)
                            <span class="text-gray-500 dark:text-gray-400 italic">{{ __('Anonymous') }}</span>
                        @else
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs" src="{{ $service->createdBy->profile_photo_url }}"/>
                                <span>{{ Str::length($service->createdBy->name) > 20 ? Str::substr($service->createdBy->name, 0, 4) . '...' . Str::substr($service->createdBy->name, -3) : $service->createdBy->name }}</span>
                                @if($service->createdBy->nostr)
                                    <flux:tooltip content="{{ __('Ersteller auf Nostr validieren (njump)') }}">
                                        <flux:link :href="'https://njump.me/'.$service->createdBy->nostr" external
                                                   variant="subtle" class="inline-flex items-center">
                                            <flux:icon.magnifying-glass variant="mini" class="text-purple-600 dark:text-purple-400"/>
                                        </flux:link>
                                    </flux:tooltip>
                                @endif
                            </div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col gap-1 text-sm">
                            <flux:tooltip content="{{ __('Created at') }}">
                                <div class="flex items-center gap-1">
                                    <flux:icon.plus variant="micro" class="text-green-600 dark:text-green-400"/>
                                    <span
                                        class="text-gray-600 dark:text-gray-400">{{ $service->created_at->asDateTime() }}</span>
                                </div>
                            </flux:tooltip>
                            @if($service->created_at->ne($service->updated_at))
                                <flux:tooltip content="{{ __('Updated at') }}">
                                    <div class="flex items-center gap-1">
                                        <flux:icon.pencil variant="micro" class="text-blue-600 dark:text-blue-400"/>
                                        <span
                                            class="text-gray-600 dark:text-gray-400">{{ $service->updated_at->asDateTime() }}</span>
                                    </div>
                                </flux:tooltip>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
