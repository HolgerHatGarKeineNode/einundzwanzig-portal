<?php

use App\Attributes\SeoDataAttribute;
use App\Models\SelfHostedService;
use App\Traits\SeoTrait;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'services_landingpage')]
class extends Component {
    use SeoTrait;

    public SelfHostedService $service;

    public $country = 'de';

    public function mount(): void
    {
        $this->service->load('createdBy');
        $this->country = request()->route('country', config('app.domain_country'));
    }

    /**
     * Hier stand `auth()->id() === $this->service->created_by`. Das war enger als die
     * Policy: `SelfHostedServicePolicy` kennt den Super-Admin, diese Zeile nicht — er
     * hatte das Recht und lief trotzdem in den 403. Seit P1 gilt die Policy, seit hier
     * auch fuer diese Aktion.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->service);

        $this->service->delete();

        session()->flash('status', __('Service erfolgreich gelöscht!'));

        redirect()->route('services.index', ['country' => $this->country]);
    }

    public function with(): array
    {
        return [
            'service' => $this->service,
        ];
    }
}; ?>

@section('meta')
    @php
        $SEOData = SeoDataAttribute::getData('meetups_landingpage');
        $SEOData->title = $this->service->name;
        $SEOData->description = $this->service->intro ? str($this->service->intro)->limit(50) : $SEOData->description;
    @endphp
    {!! seo($SEOData)->render() !!}
@endsection

<div class="container mx-auto px-4 py-8 max-w-5xl">
    <x-service-disclaimer/>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="xl">{{ $service->name }}</flux:heading>
            {{-- Knopf und Aktion fragen dieselbe Policy. Vorher stand hier
                 `auth()->id() === $service->created_by`: der Super-Admin durfte, sah aber
                 keinen Knopf. --}}
            @auth
                @canany(['update', 'delete'], $service)
                    <div class="flex gap-2">
                        @can('update', $service)
                            <flux:button :href="route_with_country('services.edit', ['service' => $service])"
                                         variant="primary" icon="pencil">
                                {{ __('Bearbeiten') }}
                            </flux:button>
                        @endcan
                        @can('delete', $service)
                            <flux:modal.trigger name="delete-service">
                                <flux:button variant="danger" icon="trash">
                                    {{ __('Löschen') }}
                                </flux:button>
                            </flux:modal.trigger>
                        @endcan
                    </div>
                @endcanany
            @endauth
        </div>

        @if($service->type)
            <flux:badge size="lg" color="{{ $service->type->color() }}">
                {{ $service->type->label() }}
            </flux:badge>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Description -->
            @if($service->intro)
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">{{ __('Beschreibung') }}</flux:heading>
                    <div class="prose dark:prose-invert max-w-none">
                        {{ $service->intro }}
                    </div>
                </flux:card>
            @endif

            <!-- Contact Information -->
            @if($service->contact)
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">{{ __('Kontakt') }}</flux:heading>
                    <div class="prose dark:prose-invert max-w-none">
                        {{ $service->contact }}
                    </div>
                </flux:card>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Links -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Zugriff') }}</flux:heading>
                <div class="flex flex-col gap-3">
                    @if($service->url_clearnet)
                        <div class="flex items-center justify-between gap-2">
                            <flux:link :href="$service->url_clearnet" external
                                       class="text-blue-600 dark:text-blue-400 flex items-center gap-2">
                                <flux:icon.globe-alt variant="mini"/>
                                <span>Clearnet</span>
                            </flux:link>
                            <div x-copy-to-clipboard="'{{ $service->url_clearnet }}'">
                                <flux:button icon="clipboard" size="xs" variant="ghost" class="cursor-pointer">
                                    {{ __('Copy') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                    @if($service->url_onion)
                        <div class="flex items-center justify-between gap-2">
                            <flux:link :href="$service->url_onion" external
                                       class="text-purple-600 dark:text-purple-400 flex items-center gap-2">
                                <flux:icon.lock-closed variant="mini"/>
                                <span>Onion / Tor</span>
                            </flux:link>
                            <div x-copy-to-clipboard="'{{ $service->url_onion }}'">
                                <flux:button icon="clipboard" size="xs" variant="ghost" class="cursor-pointer">
                                    {{ __('Copy') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                    @if($service->url_i2p)
                        <div class="flex items-center justify-between gap-2">
                            <flux:link :href="$service->url_i2p" external
                                       class="text-green-600 dark:text-green-400 flex items-center gap-2">
                                <flux:icon.link variant="mini"/>
                                <span>I2P</span>
                            </flux:link>
                            <div x-copy-to-clipboard="'{{ $service->url_i2p }}'">
                                <flux:button icon="clipboard" size="xs" variant="ghost" class="cursor-pointer">
                                    {{ __('Copy') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                    @if($service->url_pkdns)
                        <div class="flex items-center justify-between gap-2">
                            <flux:link :href="$service->url_pkdns" external
                                       class="text-orange-600 dark:text-orange-400 flex items-center gap-2">
                                <flux:icon.link variant="mini"/>
                                <span>pkdns</span>
                            </flux:link>
                            <div x-copy-to-clipboard="'{{ $service->url_pkdns }}'">
                                <flux:button icon="clipboard" size="xs" variant="ghost" class="cursor-pointer">
                                    {{ __('Copy') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                    @if($service->ip)
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 font-mono text-sm text-gray-700 dark:text-gray-300">
                                <flux:icon.server variant="mini"/>
                                <span>{{ $service->ip }}</span>
                            </div>
                            <div x-copy-to-clipboard="'{{ $service->ip }}'">
                                <flux:button icon="clipboard" size="xs" variant="ghost" class="cursor-pointer">
                                    {{ __('Copy') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Metadata -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Informationen') }}</flux:heading>
                <div class="space-y-4 text-sm">
                    <!-- Created By -->
                    <div>
                        <div class="text-gray-500 dark:text-gray-400 mb-1">{{ __('Erstellt von') }}</div>
                        @if($service->anon || !$service->createdBy)
                            <span class="text-gray-500 dark:text-gray-400 italic">{{ __('Anonymous') }}</span>
                        @else
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs" src="{{ $service->createdBy->profile_photo_url }}"/>
                                <span class="font-medium">{{ $service->createdBy->name }}</span>
                            </div>
                            @if($service->createdBy->nostr)
                                <flux:link :href="'https://njump.me/'.$service->createdBy->nostr" external
                                           variant="subtle"
                                           class="mt-2 inline-flex items-center gap-1 text-purple-600 dark:text-purple-400">
                                    <flux:icon.magnifying-glass variant="mini"/>
                                    <span>{{ __('Ersteller auf Nostr validieren (njump)') }}</span>
                                </flux:link>
                            @endif
                        @endif
                    </div>

                    <!-- Created At -->
                    <div>
                        <div class="text-gray-500 dark:text-gray-400 mb-1">{{ __('Erstellt am') }}</div>
                        <div class="flex items-center gap-1">
                            <flux:icon.plus variant="micro" class="text-green-600 dark:text-green-400"/>
                            <span>{{ $service->created_at->asDateTime() }}</span>
                        </div>
                    </div>

                    <!-- Updated At -->
                    @if($service->created_at->ne($service->updated_at))
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 mb-1">{{ __('Zuletzt aktualisiert') }}</div>
                            <div class="flex items-center gap-1">
                                <flux:icon.pencil variant="micro" class="text-blue-600 dark:text-blue-400"/>
                                <span>{{ $service->updated_at->asDateTime() }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Back Button -->
            <flux:button :href="route_with_country('services.index')" variant="ghost" icon="arrow-left" class="w-full">
                {{ __('Zurück zur Übersicht') }}
            </flux:button>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-service" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Service löschen?') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Möchten Sie den Service wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:spacer/>
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
            </flux:modal.close>
            <flux:button wire:click="delete" variant="danger">
                {{ __('Löschen') }}
            </flux:button>
        </div>
    </flux:modal>
</div>
