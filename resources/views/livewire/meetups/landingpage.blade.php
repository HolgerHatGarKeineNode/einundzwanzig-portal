<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\SeoTrait;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'meetups_landingpage')]
class extends Component {
    use SeoTrait;

    public Meetup $meetup;

    public $country = 'de';

    public function mount(): void
    {
        $this->country = request()->route('country');
    }

    public function with(): array
    {
        return [
            'meetup' => $this->meetup,
            'events' => $this->meetup
                ->meetupEvents()
                ->where('start', '>=', now())
                ->orderBy('start', 'asc')
                ->get(),
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column: Meetup Details -->
        <div class="space-y-6">
            <div class="flex items-center space-x-4">
                <flux:avatar class="[:where(&)]:size-32 [:where(&)]:text-base" size="xl"
                             src="{{ $meetup->getFirstMediaUrl('logo') }}"/>
                <div class="space-y-2">
                    <flux:heading size="xl" class="mb-4">{{ $meetup->name }}</flux:heading>
                    <flux:subheading class="text-gray-600 dark:text-gray-400">
                        {{ $meetup->city->name }}, {{ $meetup->city->country->name }}
                    </flux:subheading>
                    <flux:button class="cursor-pointer"
                                 x-copy-to-clipboard="'{{ route('ics', ['meetup' => $meetup]) }}'"
                                 icon="calendar-date-range">{{ __('Kalender-Stream-URL kopieren') }}</flux:button>
                </div>
            </div>

            @if($meetup->intro)
                <div>
                    <flux:heading size="lg" class="mb-2">{{ __('Über uns') }}</flux:heading>
                    <x-markdown class="prose whitespace-pre-wrap">{!! $meetup->intro !!}</x-markdown>
                </div>
            @endif

            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Kontakt & Links') }}</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @if($meetup->webpage)
                        <flux:button href="{{ $meetup->webpage }}" target="_blank" variant="ghost"
                                     class="justify-start">
                            <flux:icon.globe-alt class="w-5 h-5 mr-2"/>
                            Webseite
                        </flux:button>
                    @endif

                    @if($meetup->telegram_link)
                        <flux:button href="{{ $meetup->telegram_link }}" target="_blank" variant="ghost"
                                     class="justify-start">
                            <flux:icon.chat-bubble-left-right class="w-5 h-5 mr-2"/>
                            Telegram
                        </flux:button>
                    @endif

                    @if($meetup->twitter_username)
                        <flux:button href="https://twitter.com/{{ $meetup->twitter_username }}" target="_blank"
                                     variant="ghost" class="justify-start">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                            Twitter/X
                        </flux:button>
                    @endif

                    @if($meetup->matrix_group)
                        <flux:button href="{{ $meetup->matrix_group }}" target="_blank" variant="ghost"
                                     class="justify-start">
                            <flux:icon.hashtag class="w-5 h-5 mr-2"/>
                            Matrix
                        </flux:button>
                    @endif

                    @if($meetup->signal)
                        <flux:button href="{{ $meetup->signal }}" target="_blank" variant="ghost" class="justify-start">
                            <flux:icon.phone class="w-5 h-5 mr-2"/>
                            Signal
                        </flux:button>
                    @endif

                    @if($meetup->simplex)
                        <flux:button href="{{ $meetup->simplex }}" target="_blank" variant="ghost"
                                     class="justify-start">
                            <flux:icon.chat-bubble-oval-left-ellipsis class="w-5 h-5 mr-2"/>
                            SimpleX
                        </flux:button>
                    @endif

                    @if($meetup->nostr)
                        <div class="col-span-full">
                            <flux:heading size="sm" class="mb-2">Nostr</flux:heading>
                            <code x-copy-to-clipboard="'{{ $meetup->nostr }}'"
                                  class="cursor-pointer block p-2 bg-gray-100 dark:bg-gray-800 rounded text-xs break-all">{{ $meetup->nostr }}</code>
                        </div>
                    @endif
                </div>

                @if($meetup->community)
                    <div>
                        <flux:heading size="sm" class="mb-2">Community</flux:heading>
                        <p class="text-gray-700 dark:text-gray-300">{{ $meetup->community }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Column: Map -->
        <div>
            <style>
                #meetup-map {
                    height: 70vh;
                    min-height: 500px;
                    z-index: 0 !important;
                }

                #meetup-map:focus {
                    outline: none;
                }
            </style>
            @php
                $attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
            @endphp
            <div>
                <flux:heading size="lg" class="mb-4">{{ __('Standort') }}</flux:heading>
            </div>
            <div x-data="{
                    meetup: @js($meetup),
                    initializeMap() {
                        const map = L.map($refs.map, {
                            scrollWheelZoom: false
                        }).setView([this.meetup.city.latitude, this.meetup.city.longitude], 8);

                        L.tileLayer('https://tile.openstreetmap.de/{z}/{x}/{y}.png', {
                            minZoom: 0,
                            maxZoom: 18,
                            attribution: '{{ $attribution }}'
                        }).addTo(map);

                        // Custom BTC icon
                        const btcIcon = L.icon({
                            iconUrl: '/img/btc_marker.png',
                            iconSize: [32, 32],
                            iconAnchor: [16, 32],
                            popupAnchor: [0, -32],
                            shadowUrl: null
                        });

                        L.marker([this.meetup.city.latitude, this.meetup.city.longitude], {
                            icon: btcIcon
                        })
                            .bindPopup(this.meetup.name)
                            .addTo(map);

                        // CTRL + scroll wheel zoom
                        const container = map.getContainer();
                        container.addEventListener('wheel', function (e) {
                            e.preventDefault();
                            if (e.ctrlKey) {
                                const delta = e.deltaY > 0 ? -1 : 1;
                                map.setZoom(map.getZoom() + delta, { animate: true });
                            }
                        }, { passive: false });
                    }
                }"
                 x-init="initializeMap()"
            >
                <div id="meetup-map" x-ref="map"></div>
                <p class="text-sm text-gray-500 mt-2">{{ __('Zoom = STRG+Scroll') }}</p>
            </div>
        </div>
    </div>

    <!-- Events Section -->
    @if($events->isNotEmpty())
        <div class="mt-16">
            <div class="flex items-center space-x-4 mb-6">
                <flux:heading size="xl">{{ __('Kommende Veranstaltungen') }}</flux:heading>
                @if(auth()->user() && auth()->user()->meetups()->find($meetup->id)?->exists)
                    <flux:button :href="route_with_country('meetups.events.create', ['meetup' => $meetup])"
                                 variant="primary" icon="calendar">
                        {{ __('Neues Event erstellen') }}
                    </flux:button>
                @endif
                <flux:button class="cursor-pointer" x-copy-to-clipboard="'{{ route('ics', ['meetup' => $meetup]) }}'"
                             icon="calendar-date-range">{{ __('Kalender-Stream-URL kopieren') }}</flux:button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($events as $event)
                    <flux:card size="sm" class="h-full flex flex-col">
                        <flux:heading class="flex items-center gap-2">
                            {{ $event->start->format('d.m.Y') }}
                        </flux:heading>

                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <flux:icon.clock class="inline w-4 h-4"/>
                            {{ $event->start->format('H:i') }} Uhr
                        </flux:text>

                        @if($event->location)
                            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <flux:icon.map-pin class="inline w-4 h-4"/>
                                {{ $event->location }}
                            </flux:text>
                        @endif

                        @if($event->description)
                            <flux:text class="mt-2">{{ Str::limit($event->description, 100) }}</flux:text>
                        @endif

                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <div class="text-xs text-zinc-500 flex items-center gap-2">
                                <span>{{ count($event->attendees ?? []) }} {{ __('Zusagen') }}</span>
                                <flux:separator vertical/>
                                <span>{{ count($event->might_attendees ?? []) }} {{ __('Vielleicht') }}</span>
                            </div>
                        </flux:text>

                        <div class="mt-auto pt-4 flex gap-2">
                            <flux:button
                                :href="route('meetups.landingpage-event', ['meetup' => $meetup->slug, 'event' => $event->id, 'country' => $country])"
                                size="xs"
                                variant="primary"
                                class="flex-1"
                            >
                                {{ __('Öffnen/RSVP') }}
                            </flux:button>
                            @if($meetup->belongsToMe)
                                <flux:button
                                    :href="route_with_country('meetups.events.edit', ['meetup' => $meetup, 'event' => $event])"
                                    size="xs"
                                    variant="ghost"
                                    icon="pencil"
                                >
                                    {{ __('Bearbeiten') }}
                                </flux:button>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @else
        <div class="mt-16">
            <div class="flex items-center space-x-4 mb-6">
                @if(auth()->user() && auth()->user()->meetups()->find($meetup->id)?->exists)
                    <flux:button :href="route_with_country('meetups.events.create', ['meetup' => $meetup])"
                                 variant="primary" icon="calendar">
                        {{ __('Neues Event erstellen') }}
                    </flux:button>
                @endif
            </div>
        </div>
    @endif
</div>
