@props([
    // Das Model mit der `nostr_coordinate`-Spalte: Meetup (kind 31924) oder MeetupEvent
    // (kind 31923). Die Kind-Unterscheidung kommt aus der gespeicherten Koordinate
    // selbst, nicht aus einem Prop — so kann sie nicht zum Datensatz widersprechen.
    'record',
    // Ob das Meetup Nostr-Veröffentlichung eingeschaltet hat. Beim MeetupEvent ist das
    // der Schalter des Meetups: ein Termin hat keinen eigenen (siehe Migration
    // 2026_08_29_170904).
    'publishingEnabled' => false,
])

@php
    use App\Support\NostrCalendarAddress;

    $address = NostrCalendarAddress::fromCoordinate($record->nostr_coordinate ?? null);
@endphp

{{--
    Drei Zustände, und der mittlere ist der wichtigste.

    Issue #49: Ein Organisator schaltet „Auf Nostr veröffentlichen" ein, sucht seine
    Veranstaltung auf fünf Relays und findet nichts. Ohne Anzeige kann er nicht
    unterscheiden, ob nichts gesendet wurde oder ob er nur falsch sucht — dieselbe
    Fehlerklasse wie die leeren Zustände aus Issue #45. Deshalb:

      1. Schalter aus  -> gar nichts. Wer nicht veröffentlicht, braucht keine Adresse.
      2. Schalter an, keine Koordinate -> ausdrücklich „noch nicht veröffentlicht".
         Nie ein Platzhalter, der wie eine Adresse aussieht.
      3. Koordinate da -> die Adresse, kopierbar, mit den Relays, an die gesendet wurde,
         und mit Betrachtern, die dieses Kind nachweislich darstellen.

    Zustand 3 kann auch ohne (2) eintreten: `nostr_coordinate` bleibt stehen, wenn der
    Schalter später wieder ausgeht. Ein veröffentlichtes Ereignis lässt sich von einem
    Relay nicht zurückholen, also wird eine vorhandene Adresse weiter gezeigt — sie zu
    verstecken würde behaupten, es gäbe sie nicht mehr.
--}}

@if($address)
    {{-- data-testid statt Textprobe: die Copy ist uebersetzbar, der Haken nicht. --}}
    <div data-testid="nostr-calendar-address"
         data-nostr-kind="{{ $address->kind }}"
         class="col-span-full space-y-2">
        <flux:heading size="sm">
            {{ $address->isCalendar() ? __('Nostr-Kalender (NIP-52)') : __('Nostr-Termin (NIP-52)') }}
        </flux:heading>

        {{-- x-data ausdrücklich gesetzt: Alpine wertet Direktiven nur innerhalb eines
             initialisierten Scopes aus, und dieser Block steht in beiden Seiten an einer
             Stelle ohne umgebendes x-data. --}}
        <code x-data
              x-copy-to-clipboard="'{{ $address->naddr() }}'"
              role="button"
              tabindex="0"
              title="{{ __('In die Zwischenablage kopieren') }}"
              class="cursor-pointer block p-2 bg-gray-100 dark:bg-gray-800 rounded text-xs break-all">{{ $address->naddr() }}</code>

        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
            <span>{{ __('Ansehen bei') }}</span>
            @foreach($address->viewers() as $viewer)
                <a href="{{ $viewer['url'] }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="underline hover:text-gray-900 dark:hover:text-gray-100">{{ $viewer['label'] }}</a>
            @endforeach
        </div>

        @if($address->relays() !== [])
            {{-- Die Antwort auf „auf welchem Relay suche ich?", genau dort, wo die Frage
                 entsteht. Der naddr trägt dieselben Relays als NIP-19-Hinweis, aber nur
                 Clients lesen den; ein Mensch braucht sie lesbar. --}}
            <p class="text-xs text-gray-600 dark:text-gray-400">
                {{ __('Veröffentlicht an:') }}
                <span class="break-all">{{ implode(' · ', $address->relays()) }}</span>
            </p>
        @endif
    </div>
@elseif($publishingEnabled)
    @php
        $targetRelays = array_values((array) config('services.nostr.relays', []));
    @endphp
    <div data-testid="nostr-calendar-address-pending" class="col-span-full space-y-1">
        <flux:heading size="sm">{{ __('Nostr (NIP-52)') }}</flux:heading>
        <p class="text-xs text-gray-600 dark:text-gray-400">
            {{ __('Noch nicht auf Nostr veröffentlicht — hier erscheint die NIP-52-Adresse, sobald der Eintrag gesendet wurde.') }}
        </p>
        @if($targetRelays !== [])
            {{-- Auch im Wartezustand die Ziel-Relays nennen: Wer gleich nachsehen will,
                 wo etwas auftauchen müsste, soll nicht raten müssen (Issue #49, Frage 2). --}}
            <p class="text-xs text-gray-600 dark:text-gray-400">
                {{ __('Ziel-Relays:') }}
                <span class="break-all">{{ implode(' · ', $targetRelays) }}</span>
            </p>
        @endif
    </div>
@endif
