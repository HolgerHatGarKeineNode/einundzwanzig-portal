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
        $this->service->load('createdBy');
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function delete(): void
    {
        // Only allow deletion if user is the creator
        if (auth()->id() === $this->service->created_by) {
            $this->service->delete();

            session()->flash('status', __('Service erfolgreich gelöscht!'));

            redirect()->route('services.index', ['country' => $this->country]);
        } else {
            abort(403);
        }
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
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="xl">{{ $service->name }}</flux:heading>
            @auth
                @if(auth()->id() === $service->created_by)
                    <div class="flex gap-2">
                        <flux:button :href="route_with_country('services.edit', ['service' => $service])"
                                     variant="primary" icon="pencil">
                            {{ __('Bearbeiten') }}
                        </flux:button>
                        <flux:modal.trigger name="delete-service">
                            <flux:button variant="danger" icon="trash">
                                {{ __('Löschen') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif
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
                    <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap">
                        {{ $service->intro }}
                    </div>
                </flux:card>
            @endif

            <!-- Contact Information -->
            @if($service->contact)
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">{{ __('Kontakt') }}</flux:heading>
                    <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap">
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
                <div class="flex flex-col gap-2">
                    @if($service->url_clearnet)
                        <flux:link :href="$service->url_clearnet" external
                                   class="text-blue-600 dark:text-blue-400 flex items-center gap-2">
                            <flux:icon.globe-alt variant="mini"/>
                            <span>Clearnet</span>
                        </flux:link>
                    @endif
                    @if($service->url_onion)
                        <flux:link :href="$service->url_onion" external
                                   class="text-purple-600 dark:text-purple-400 flex items-center gap-2">
                            <flux:icon.lock-closed variant="mini"/>
                            <span>Onion / Tor</span>
                        </flux:link>
                    @endif
                    @if($service->url_i2p)
                        <flux:link :href="$service->url_i2p" external
                                   class="text-green-600 dark:text-green-400 flex items-center gap-2">
                            <flux:icon.link variant="mini"/>
                            <span>I2P</span>
                        </flux:link>
                    @endif
                    @if($service->url_pkdns)
                        <flux:link :href="$service->url_pkdns" external
                                   class="text-orange-600 dark:text-orange-400 flex items-center gap-2">
                            <flux:icon.link variant="mini"/>
                            <span>pkdns</span>
                        </flux:link>
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
                        @if($service->createdBy)
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs" src="{{ $service->createdBy->profile_photo_url }}"/>
                                <span class="font-medium">{{ $service->createdBy->name }}</span>
                            </div>
                        @else
                            <span class="text-gray-500 dark:text-gray-400 italic">{{ __('Anonymous') }}</span>
                        @endif
                    </div>

                    <!-- Created At -->
                    <div>
                        <div class="text-gray-500 dark:text-gray-400 mb-1">{{ __('Erstellt am') }}</div>
                        <div class="flex items-center gap-1">
                            <flux:icon.plus variant="micro" class="text-green-600 dark:text-green-400"/>
                            <span>{{ $service->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                    </div>

                    <!-- Updated At -->
                    @if($service->created_at->ne($service->updated_at))
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 mb-1">{{ __('Zuletzt aktualisiert') }}</div>
                            <div class="flex items-center gap-1">
                                <flux:icon.pencil variant="micro" class="text-blue-600 dark:text-blue-400"/>
                                <span>{{ $service->updated_at->format('d.m.Y H:i') }}</span>
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
