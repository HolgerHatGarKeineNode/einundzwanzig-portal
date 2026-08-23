@props([
    // Das Model mit den osm_*-Spalten: MeetupEvent, CourseEvent, BitcoinEvent, City.
    'place',
    // Adresse und abweichenden Freitext mitzeigen — auf Detailseiten ja, in Kacheln nein.
    'showAddress' => false,
])

@php
    $placeName = $place->osm_name ?: ($place->location ?? null);

    /*
     * Nur die drei OSM-Objekttypen ergeben eine gültige URL. Ein unbekannter Typ wird
     * als Klartext gezeigt, nie als Link: ein kaputter Wert darf kein href werden.
     */
    $osmUrl = $place->osm_id && in_array(mb_strtolower((string) $place->osm_type), ['node', 'way', 'relation'], true)
        ? 'https://www.openstreetmap.org/'.mb_strtolower((string) $place->osm_type).'/'.(int) $place->osm_id
        : null;

    // Der Freitext steht nur dann zusätzlich da, wenn er etwas anderes sagt als der
    // Kartenort — zweimal derselbe Name untereinander liest sich wie ein Fehler.
    $freeText = $showAddress
        && ($place->location ?? null)
        && $place->osm_name
        && mb_strtolower(trim((string) $place->location)) !== mb_strtolower(trim((string) $place->osm_name))
            ? $place->location
            : null;
@endphp

@if($placeName)
    <div {{ $attributes->merge(['class' => 'min-w-0 break-words']) }}>
        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">
            @if($osmUrl)
                {{-- Der Ortsname selbst ist der Link: ein bekanntes Kartenobjekt ist der
                     einzige Unterschied, der sich zu zeigen lohnt, und der Pfeil markiert
                     ihn ohne sich auf Farbe zu verlassen (WCAG 1.4.1).

                     py-1, nicht py-0.5: die Zeilenbox misst im Browser 19px, nicht die 20px,
                     die die Skala nahelegt — mit 0.5 bliebe das Ziel bei 23px und damit
                     einen unter den 24px aus WCAG 2.5.8. An einem inline-Element vergrößert
                     das Padding die Trefferfläche, ohne das Layout zu verschieben. --}}
                <a href="{{ $osmUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="{{ __(':place auf OpenStreetMap öffnen', ['place' => $placeName]) }}"
                   class="rounded-xs py-1 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">
                    {{ $placeName }}<flux:icon.arrow-up-right
                        class="ml-1 inline size-3.5 align-middle" aria-hidden="true"/>
                </a>
            @else
                {{ $placeName }}
            @endif
        </div>

        {{-- zinc-600/300 statt zinc-500: letzteres misst auf dem dunklen Body 3,19:1 und
             reißt damit WCAG 1.4.3. --}}
        @if($showAddress && $place->osm_address)
            <div class="line-clamp-2 text-xs text-zinc-600 dark:text-zinc-300">
                {{ $place->osm_address }}
            </div>
        @endif

        @if($freeText)
            <div class="text-xs text-zinc-600 dark:text-zinc-300">
                {{ $freeText }}
            </div>
        @endif

        {{ $slot }}
    </div>
@endif
