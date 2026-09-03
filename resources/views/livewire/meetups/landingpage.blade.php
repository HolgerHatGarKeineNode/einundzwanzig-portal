<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\SeoTrait;
use Flux\Flux;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'meetups_landingpage')]
class extends Component
{
    use SeoTrait;

    public Meetup $meetup;

    public $country = 'de';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function deleteEvent(MeetupEvent $event): void
    {
        if ($this->meetup->leadByMe) {
            $event->delete();
            $this->dispatch('event-deleted');
            Flux::modals()->close();
            $this->meetup->refresh();
        }
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

@section('meta')
    @php
        $SEOData = SeoDataAttribute::getData('meetups_landingpage');
        $SEOData->title = $this->meetup->name;
        $SEOData->description = $this->meetup->intro ? str($this->meetup->intro)->limit(50) : $SEOData->description;
        $SEOData->image = $this->meetup->getFirstMediaUrl('logo');
    @endphp
    {!! seo($SEOData)->render() !!}
@endsection

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column: Meetup Details -->
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row items-center space-x-0 sm:space-x-4 space-y-4 sm:space-y-0">
                <flux:avatar class="[:where(&)]:size-32 [:where(&)]:text-base" size="xl"
                             src="{{ $meetup->getFirstMediaUrl('logo') }}"/>
                <div class="space-y-2">
                    <flux:heading size="xl" class="mb-4">{{ $meetup->name }}</flux:heading>
                    <flux:subheading class="text-gray-600 dark:text-gray-400">
                        {{ $meetup->city->name }}, {{ $meetup->city->country->name }}
                    </flux:subheading>
                    <x-calendar-stream-picker :meetup-id="$meetup->id"/>
                    @if(auth()->check())
                        {{-- Identical condition to the list view's edit action
                             (index.blade.php, "Bearbeiten"): the update ability —
                             leader, creator, super-admin, meetup steward. Without
                             this, editing a meetup was reachable only by going back
                             to the list view. --}}
                        @if($meetup->leadByMe || auth()->user()->can('update', $meetup))
                            <div>
                                <flux:button
                                    :href="route_with_country('meetups.edit', ['meetup' => $meetup])"
                                    size="sm" variant="filled" icon="pencil">
                                    {{ __('Meetup bearbeiten') }}
                                </flux:button>
                            </div>
                        @endif
                    @endif
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
                        <flux:button href="{{ $meetup->webpage }}" target="_blank" rel="noopener noreferrer" variant="ghost"
                                     class="justify-start">
                            <flux:icon.globe-alt class="w-5 h-5 mr-2"/>
                            Webseite
                        </flux:button>
                    @endif

                    @if($meetup->telegram_link)
                        <flux:button href="{{ $meetup->telegram_link }}" target="_blank" rel="noopener noreferrer" variant="ghost"
                                     class="justify-start">
                            <flux:icon.chat-bubble-left-right class="w-5 h-5 mr-2"/>
                            Telegram
                        </flux:button>
                    @endif

                    @if($meetup->twitter_username)
                        <flux:button href="https://twitter.com/{{ $meetup->twitter_username }}" target="_blank" rel="noopener noreferrer"
                                     variant="ghost" class="justify-start">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                            Twitter/X
                        </flux:button>
                    @endif

                    @if($meetup->matrix_group)
                        <flux:button href="{{ $meetup->matrix_group }}" target="_blank" rel="noopener noreferrer" variant="ghost"
                                     class="justify-start">
                            <flux:icon.hashtag class="w-5 h-5 mr-2"/>
                            Matrix
                        </flux:button>
                    @endif

                    @if($meetup->signal)
                        <flux:button href="{{ $meetup->signal }}" target="_blank" rel="noopener noreferrer" variant="ghost" class="justify-start">
                            <flux:icon.phone class="w-5 h-5 mr-2"/>
                            Signal
                        </flux:button>
                    @endif

                    @if($meetup->simplex)
                        <flux:button href="{{ $meetup->simplex }}" target="_blank" rel="noopener noreferrer" variant="ghost"
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
                        <flux:heading size="sm" class="mb-2">{{ __('Community') }}</flux:heading>
                        <p class="text-gray-700 dark:text-gray-300">
                            @if ($meetup->community === 'bitcoin')
                                {{ __('Allgemeine Bitcoin Community') }}
                            @elseif ($meetup->community === 'einundzwanzig')
                                {{ __('EINUNDZWANZIG Community') }}
                            @else
                                {{ $meetup->community }}
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Column: Map -->
        <div>
            <style>
                /* Bounded instead of 70vh/500px: the map is the right column of the
                   lg:grid-cols-2 grid above, and the event list only starts after
                   BOTH columns, so a viewport-proportional map pushed
                   "Kommende Veranstaltungen" out of the first viewport on a
                   1080px-high desktop and put 500px of map in front of it on
                   mobile (the map column comes first in the DOM there).
                   min-height repeats the clamp floor on purpose: where clamp() is
                   unsupported the whole height declaration is dropped, and 240px
                   is then the only remaining size. */
                #meetup-map {
                    height: clamp(240px, 34vh, 420px);
                    min-height: 240px;
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
                 wire:ignore
            >
                <div class="rounded" id="meetup-map" x-ref="map"></div>
                <p class="text-sm text-gray-500 mt-2">{{ __('Zoom = STRG+Scroll') }}</p>
            </div>
        </div>
    </div>

    {{-- Events Section --}}
    @if($events->isNotEmpty())
        <div class="mt-16">
            <div class="flex flex-col sm:flex-row items-center sm:space-x-4 space-y-4 sm:space-y-0 mb-6">
                <flux:heading size="xl">{{ __('Kommende Veranstaltungen') }}</flux:heading>
                @if($meetup->leadByMe)
                    <flux:button :href="route_with_country('meetups.events.create', ['meetup' => $meetup])"
                                 variant="primary" icon="calendar">
                        {{ __('Neues Event erstellen') }}
                    </flux:button>
                @endif
                <x-calendar-stream-picker :meetup-id="$meetup->id"/>
            </div>

            {{-- Einmalig statt pro Karte: attendeesVisibleTo() löst sonst je Event
                 den update-Policy-Check (is_leader-Pivot-Query) neu aus. --}}
            @php($canSeeAttendees = $meetup->attendeesVisibleTo(auth()->user()))
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($events as $event)
                    <flux:card size="sm" class="h-full flex flex-col">
                        <flux:heading class="flex items-center gap-2">
                            {{ $event->start->asDate() }}
                        </flux:heading>

                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <flux:icon.clock class="inline w-4 h-4"/>
                            {{ $event->start->asTime() }} Uhr
                        </flux:text>

                        {{-- Einzeilig: die Kachel beantwortet "welcher Termin", der Ortsname
                             schaerft das. Die Adresse gehoert auf die Detailseite, nicht in
                             fuenf Kacheln nebeneinander. --}}
                        @if($event->osm_name || $event->location)
                            <div class="mt-1 flex items-start gap-1.5">
                                <flux:icon.map-pin class="mt-0.5 size-4 shrink-0 text-zinc-600 dark:text-zinc-300"
                                                   aria-hidden="true"/>
                                <x-osm-place :place="$event"/>
                            </div>
                        @endif

                        @if($event->description)
                            <flux:text class="mt-2">{{ Str::limit($event->description, 100) }}</flux:text>
                        @endif

                        @if($canSeeAttendees)
                            <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                <div class="text-xs text-zinc-500 flex items-center gap-2">
                                    <span>{{ trans_choice(':count Zusage|:count Zusagen', count($event->attendees ?? [])) }}</span>
                                    <flux:separator vertical/>
                                    <span>{{ trans_choice(':count Vielleicht|:count Vielleicht', count($event->might_attendees ?? [])) }}</span>
                                </div>
                            </flux:text>
                        @endif

                        <div class="mt-auto pt-4 flex gap-2">
                            <flux:button
                                :href="route('meetups.landingpage-event', ['meetup' => $meetup->slug, 'event' => $event->id, 'country' => $country])"
                                size="xs"
                                variant="primary"
                                class="flex-1"
                            >
                                {{ __('Öffnen/RSVP') }}
                            </flux:button>
                            @if($meetup->leadByMe)
                                <flux:button
                                    :href="route_with_country('meetups.events.edit', ['meetup' => $meetup, 'event' => $event])"
                                    size="xs"
                                    variant="ghost"
                                    icon="pencil"
                                >
                                    {{ __('Bearbeiten') }}
                                </flux:button>
                                <flux:modal.trigger name="delete-event-{{ $event->id }}">
                                    <flux:button
                                        class="cursor-pointer"
                                        size="xs"
                                        variant="danger"
                                        icon="trash"
                                    >
                                        {{ __('Entfernen') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:modal name="delete-event-{{ $event->id }}" variant="flyout">
                                    <form wire:submit="deleteEvent({{ $event->id }})" class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">{{ __('Event löschen?') }}</flux:heading>
                                            <flux:subheading>
                                                {{ __('Möchtest du das Event vom') }} {{ $event->start->asDate() }} {{ __('wirklich löschen?') }}
                                            </flux:subheading>
                                            <flux:subheading class="mt-2">
                                                {{ __('Diese Aktion kann nicht rückgängig gemacht werden.') }}
                                            </flux:subheading>
                                        </div>
                                        <div class="flex gap-2">
                                            <flux:spacer/>
                                            <flux:modal.close>
                                                <flux:button class="cursor-pointer"
                                                             variant="ghost">{{ __('Abbrechen') }}</flux:button>
                                            </flux:modal.close>
                                            <flux:button type="submit" class="cursor-pointer"
                                                         variant="danger">{{ __('Entfernen') }}</flux:button>
                                        </div>
                                    </form>
                                </flux:modal>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @else
        <div class="mt-16">
            <div class="flex items-center space-x-4 mb-6">
                @if($meetup->leadByMe)
                    <flux:button :href="route_with_country('meetups.events.create', ['meetup' => $meetup])"
                                 variant="primary" icon="calendar">
                        {{ __('Neues Event erstellen') }}
                    </flux:button>
                @endif
            </div>
        </div>
    @endif
</div>
