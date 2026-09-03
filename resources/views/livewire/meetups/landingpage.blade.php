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
    {{-- Identity row: who and where, in one band. Everything a visitor needs to
         confirm they are on the right page, and nothing that delays the list. --}}
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

    {{-- Upcoming events, full width and directly under the identity row (issue #45).

         Measured on a FULL meetup — long intro, all six contact links, nostr and
         community — with this section still sitting below both grid columns:

             viewport     details col   map col   events heading   first card
             1920x1080      892px        435px       y=1026          y=1088
             1440x900       964px        374px       y=1098          y=1160

         The map was never the binding constraint. A grid takes the height of its
         TALLER column, and that is the details column by 457px at 1920 and 590px at
         1440 — so shrinking or even deleting the map could not have moved the list up
         by a single pixel. Only lifting the list out from behind both columns does,
         which is the "reposition" the issue asks for. The map stays at full size
         rather than collapsing: a collapsed map costs every visitor a click in order
         to save scrolling for some.

         This also fixes the mobile order for free. At grid-cols-1 the DOM order is the
         visual order, so phone users previously scrolled the intro, six contact
         buttons and the entire map before reaching the first date.

         The heading row is shared by both branches on purpose: before, the empty case
         rendered neither heading nor calendar action, which is half of why an empty
         meetup looked broken. --}}
    <div class="mt-8" data-testid="upcoming-events">
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

        @if($events->isNotEmpty())

            {{-- Einmalig statt pro Karte: attendeesVisibleTo() löst sonst je Event
                 den update-Policy-Check (is_leader-Pivot-Query) neu aus.

                 Deliberately the block form, and this is load-bearing.

                 Blade collects raw PHP blocks before anything else, with
                 /(?<!@)@php(.*?)@endphp/s. The single-line parenthesised form still
                 matches the opening @php of that pattern, so it pairs with the NEXT
                 real @endphp further down the file and swallows everything in between.
                 Once this section moved above the details grid, that next @endphp
                 became the map column's $attribution block near the end of the file —
                 so the whole card grid vanished from the compiled template: output fell
                 from 100KB to 35KB and left a conditional unclosed, and the page died
                 with "unexpected end of file, expecting endif".

                 Verified rather than assumed: with the single-line form restored and
                 only that later @php ... @endphp block deleted, the file compiles again
                 at 100KB. It compiled before this change only because the single-line
                 form then sat AFTER the map block, with no later @endphp left to pair
                 with. Paired block form has no opening token to steal and is safe in
                 either position. --}}
            @php
                $canSeeAttendees = $meetup->attendeesVisibleTo(auth()->user());
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($events as $event)
                    <flux:card size="sm" class="h-full flex flex-col">
                        <flux:heading class="flex items-center gap-2">
                            {{ $event->start->asDate() }}
                            {{-- Series marker (issue #43). `recurrence_group` is the only
                                 reliable series identity: the 2026_08_25_194948 migration
                                 backfilled that column alone, so events of pre-P5 series
                                 carry `recurrence_type = null` and would be missed by it.

                                 `inset="top bottom"` is Flux's own mechanism for an inline
                                 badge: it cancels the badge's py-1 out of the layout box, so
                                 a series card's heading stays exactly as tall as a
                                 badge-free neighbour's and the grid row does not grow.
                                 `shrink-0` keeps the badge from being squeezed by the date
                                 beside it, since flux:badge is whitespace-nowrap. --}}
                            @if($event->recurrence_group !== null)
                                <flux:badge size="sm" color="zinc" icon="arrow-path"
                                            inset="top bottom" class="shrink-0"
                                            data-testid="series-badge">{{ __('Serie') }}</flux:badge>
                            @endif
                        </flux:heading>

                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <flux:icon.clock class="inline w-4 h-4"/>
                            {{ __(':time Uhr', ['time' => $event->start->asTime()]) }}
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

                        {{-- `flex-wrap` alone would not have fixed the leader row: with
                             `flex-1` the primary button has flex-basis 0, so it counts as
                             zero when the browser decides where to break the line, all
                             three buttons stay on one line, and `min-width: auto` then
                             floors them back to 312px of content in a 254px box —
                             "Entfernen" spilling into the neighbouring card. `basis-full`
                             gives the primary a real basis so it claims the first line and
                             the two leader actions wrap below it. The visitor view is
                             unchanged: with a single child, basis-full renders exactly as
                             flex-1 did. `gap-2` already supplies the 8px row gap. --}}
                        <div class="mt-auto pt-4 flex flex-wrap gap-2">
                            <flux:button
                                :href="route('meetups.landingpage-event', ['meetup' => $meetup->slug, 'event' => $event->id, 'country' => $country])"
                                size="xs"
                                variant="primary"
                                class="basis-full"
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
        @else
            {{-- Before this, the empty case rendered a bare box: no heading, no text,
                 nothing — so a visitor could not tell "nothing is scheduled" from
                 "this page is broken". A measured finding from the #45 audit.

                 The wording is the dashboard's existing key rather than a new one: the
                 same fact should read the same everywhere in the product, and that key
                 is already translated into all nine languages with each one's own noun
                 for an occurrence (cs "termíny", nl "afspraken", lv "pasākumu", hu
                 "időpontok"). The leader gets one extra line, and it deliberately does
                 not repeat the button next to it — it says the thing the button cannot:
                 that this is exactly what visitors are seeing. --}}
            <div class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"
                 data-testid="no-upcoming-events">
                <flux:icon.calendar class="mt-0.5 size-4 shrink-0" aria-hidden="true"/>
                <div>
                    <p>{{ __('Keine bevorstehenden Termine') }}</p>
                    @if($meetup->leadByMe)
                        <p class="mt-1">{{ __('Besucher sehen an dieser Stelle dasselbe.') }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Everything below the list is reference material: read once, then not again.
         It keeps the two-column shape it always had, one rank down. --}}
    <div class="mt-16 grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column: Meetup Details -->
        <div class="space-y-6">
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
                            {{ __('Webseite') }}
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

</div>
