@props([
    // Das Model mit den osm_*-Spalten: MeetupEvent, CourseEvent, BitcoinEvent.
    'place',
    // 16 zeigt Strassenzuege samt Hausnummern — die Aufloesung, in der ein
    // Veranstaltungsort tatsaechlich wiederzuerkennen ist.
    'zoom' => 16,
    'label' => null,
])

@php
    /*
     * Ohne Koordinaten gibt es nichts zu zeigen. Das ist der Normalfall fuer jeden
     * Termin, der vor der OSM-Auswahl angelegt wurde: die Komponente rendert dann
     * gar nichts und die Seite sieht aus wie vorher.
     */
    $lat = $place->osm_lat !== null ? (float) $place->osm_lat : null;
    $lon = $place->osm_lon !== null ? (float) $place->osm_lon : null;
    $placeName = $label ?: ($place->osm_name ?: ($place->location ?? null));

    $osmUrl = $place->osm_id && in_array(mb_strtolower((string) $place->osm_type), ['node', 'way', 'relation'], true)
        ? 'https://www.openstreetmap.org/'.mb_strtolower((string) $place->osm_type).'/'.(int) $place->osm_id
        : 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lon.'#map='.$zoom.'/'.$lat.'/'.$lon;
@endphp

@if($lat !== null && $lon !== null)
    <div {{ $attributes->merge(['class' => 'space-y-2']) }}>
        {{-- wire:ignore: die Seite ist eine Livewire-Komponente, und jede RSVP-Aktion
             rendert sie neu. Ohne das Attribut morpht Livewire den Kartencontainer und
             Leaflet wirft beim zweiten Aufbau "Map container is already initialized".
             Ausserhalb von Livewire ist das Attribut wirkungslos. --}}
        <div
            wire:ignore
            x-data="{
                initVenueMap() {
                    // Zweiter Aufruf am selben Knoten (Alpine-Neuinitialisierung nach
                    // wire:navigate) waere sonst ein harter Fehler.
                    if ($refs.venueMap._leaflet_id) {
                        return;
                    }

                    // Leaflets eigene Animationen laufen ueber CSS-Transforms und
                    // ignorieren die Systemeinstellung — deshalb hier abschalten.
                    const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                    const map = L.map($refs.venueMap, {
                        scrollWheelZoom: false,
                        zoomAnimation: !calm,
                        fadeAnimation: !calm,
                        markerZoomAnimation: !calm,
                    }).setView([@js($lat), @js($lon)], @js($zoom));

                    L.tileLayer('https://tile.openstreetmap.de/{z}/{x}/{y}.png', {
                        minZoom: 0,
                        maxZoom: 18,
                        attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a> contributors',
                    }).addTo(map);

                    L.marker([@js($lat), @js($lon)], {
                        icon: L.icon({
                            iconUrl: '/img/btc_marker.png',
                            iconSize: [32, 32],
                            iconAnchor: [16, 32],
                        }),
                        // Der Ortsname liegt als title auf dem Marker, damit die Karte
                        // nicht die einzige Quelle der Information ist.
                        title: @js($placeName ?? ''),
                        alt: @js($placeName ?? ''),
                        keyboard: false,
                    }).addTo(map);
                },
            }"
            x-init="initVenueMap()"
        >
            {{-- aria-hidden: die Kachelgrafik traegt fuer Screenreader nichts bei. Die
                 Information steht als Text daneben und als Link darunter. --}}
            <div x-ref="venueMap"
                 aria-hidden="true"
                 class="h-56 w-full rounded-lg border border-zinc-200 dark:border-zinc-700"
                 style="z-index: 0;"></div>
        </div>

        <flux:link :href="$osmUrl" external variant="subtle" class="text-xs">
            {{ __('Auf OpenStreetMap öffnen') }}
        </flux:link>
    </div>
@endif
