@props([
    // Nur fuer die per-Meetup-Feeds (landingpage, landingpage-event) gesetzt;
    // null baut den Alle-Meetups-Feed (index).
    'meetupId' => null,
])

@php
    /*
     * Defaults kommen aus genau den Quellen, die der Besucher schon gewaehlt hat —
     * dieselben, die Country\Chooser, Language\Selector und Timezone\Chooser lesen.
     * Der Picker AENDERT keine davon: er liest sie nur einmal beim Rendern, alles
     * Weitere passiert client-seitig in Alpine (siehe x-data unten) und ist mit dem
     * naechsten Seitenaufruf wieder weg.
     */
    $countries = \App\Models\Country::query()->orderBy('name')->get(['code', 'name']);

    $defaultCountry = mb_strtolower((string) request()->route('country', config('app.domain_country', 'de')));

    $langCountry = (string) session('lang_country', config('lang-country.fallback', 'en-GB'));
    $defaultLanguage = str($langCountry)->before('-')->lower()->value();

    $supportedLanguages = config('lang-country.languages', []);
    if (! array_key_exists($defaultLanguage, $supportedLanguages)) {
        $defaultLanguage = array_key_exists('en', $supportedLanguages) ? 'en' : (string) array_key_first($supportedLanguages);
    }

    // Timezone/Chooser rendert nur eingeloggt (@if(auth()->check())) — hier ebenso:
    // ausgeloggt gibt es keine gespeicherte Praeferenz, nur die Domain-Zeitzone.
    $defaultTimezone = auth()->check() && auth()->user()->timezone
        ? (string) auth()->user()->timezone
        : (string) config('app.domain_timezone', 'UTC');

    $timezones = \DateTimeZone::listIdentifiers();

    $baseUrl = route('ics', $meetupId ? ['meetup' => $meetupId] : []);

    // Eindeutig je Instanz: landingpage.blade.php rendert diese Komponente zweimal
    // auf derselben Seite, und ein statisches data-testid waere dort mehrdeutig.
    $testIdPrefix = 'calendar-stream-'.\Illuminate\Support\Str::random(8);
@endphp

{{-- `triggerLabel` carries the language and the timezone, deliberately NOT the
     country: the primary copy action deletes `country` from the URL (see
     `buildUrl` below), so a trigger advertising DE would describe a feed the
     button next to it does not produce. The country is shown on the scoped copy
     button instead, where it actually applies — hence `scopedCopyTemplate`,
     whose `:country` placeholder is filled client-side so it follows the select
     without a round trip. --}}
<flux:dropdown
    position="bottom"
    align="start"
    x-data="{
        country: {!! \Illuminate\Support\Js::from($defaultCountry)->toHtml() !!},
        language: {!! \Illuminate\Support\Js::from($defaultLanguage)->toHtml() !!},
        timezone: {!! \Illuminate\Support\Js::from($defaultTimezone)->toHtml() !!},
        baseUrl: {!! \Illuminate\Support\Js::from($baseUrl)->toHtml() !!},
        scopedCopyTemplate: {!! \Illuminate\Support\Js::from(__('Kalenderlink kopieren (nur :country)'))->toHtml() !!},
        get triggerLabel() {
            return this.language.toUpperCase() + ' · ' + this.timezone;
        },
        get scopedCopyLabel() {
            return this.scopedCopyTemplate.replace(':country', this.country.toUpperCase());
        },
        buildUrl(scopeToCountry) {
            const url = new URL(this.baseUrl, window.location.origin);
            url.searchParams.set('language', this.language);
            url.searchParams.set('timezone', this.timezone);
            if (scopeToCountry) {
                url.searchParams.set('country', this.country);
            } else {
                url.searchParams.delete('country');
            }
            return url.toString();
        },
    }"
>
    {{-- The trigger names the action, not the state: "DE · DE · Europe/Berlin"
         told a visitor nothing about what the button opens. The selected codes
         stay visible as a secondary line, because they are what distinguishes
         this trigger from a plain link — but they are no longer the label.

         Same width problem as the two copy buttons below: the timezone is data
         driven (`\DateTimeZone::listIdentifiers()` has 419 entries, longest 30
         characters, "America/Argentina/Buenos_Aires"), so Flux' default
         `whitespace-nowrap` plus the fixed `h-10` (button/index.blade.php:65,73)
         would push this button past the edge of its container — here next to a
         128px avatar on the meetup landing page. `!whitespace-normal !h-auto`
         lets it wrap instead; `min-h-11` keeps the 44px touch target. --}}
    <flux:button
        align="start"
        class="cursor-pointer min-h-11 !h-auto !whitespace-normal py-2 text-start"
        icon="calendar-date-range"
        data-testid="{{ $testIdPrefix }}-trigger"
    >
        <span class="flex flex-col items-start leading-tight">
            <span>{{ __('Kalender abonnieren') }}</span>
            <span class="text-xs font-normal opacity-70" x-text="triggerLabel"></span>
        </span>
    </flux:button>

    <flux:popover class="w-[20rem] max-w-[calc(100vw-2rem)] space-y-4" data-testid="{{ $testIdPrefix }}-panel">
        <flux:field>
            <flux:label>{{ __('Land') }}</flux:label>
            <flux:select x-model="country" class="min-h-11" data-testid="{{ $testIdPrefix }}-country">
                @foreach($countries as $option)
                    <flux:select.option value="{{ mb_strtolower($option->code) }}">
                        {{ $option->name }} ({{ strtoupper($option->code) }})
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Sprache') }}</flux:label>
            <flux:select x-model="language" class="min-h-11" data-testid="{{ $testIdPrefix }}-language">
                @foreach($supportedLanguages as $code => $data)
                    <flux:select.option value="{{ $code }}">{{ $data['name'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Zeitzone') }}</flux:label>
            <flux:select x-model="timezone" class="min-h-11" data-testid="{{ $testIdPrefix }}-timezone">
                @foreach($timezones as $tz)
                    <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <div class="flex flex-col gap-2 pt-1">
            {{-- Owner-Vorgabe (Issue-Kommentar): zwei Buttons statt einem — der
                 Country-Select filtert wahlweise den Inhalt mit, statt nur Sprache
                 und Zeitzone zu setzen.

                 Flux' Standardklassen fuer flux:button setzen `whitespace-nowrap`
                 und eine feste `h-10` (button/index.blade.php:65,73) — bei jeder
                 Uebersetzung, die laenger ist als die Popover-Breite, schiebt das
                 den Button ueber den Rand statt umzubrechen. `w-full` plus die
                 `!`-Overrides (bereits Konvention im Projekt, siehe
                 layouts/app/header.blade.php:29) lassen den Text stattdessen auf
                 zwei Zeilen umbrechen; `min-h-11`/`!h-auto` halten dabei sowohl
                 das 44px-Touch-Target (einzeilig) als auch die noetige Hoehe fuer
                 zwei Zeilen ein. `align="start"` haelt beide Buttons buendig am
                 linken Rand, damit eine Liste mit unterschiedlich langen Labels
                 nicht in der Mitte ausfranst. --}}
            <flux:button
                align="start"
                class="cursor-pointer min-h-11 !h-auto w-full !whitespace-normal py-2"
                x-copy-to-clipboard="buildUrl(false)"
                data-testid="{{ $testIdPrefix }}-copy-all"
            >
                {{ __('Kalenderlink kopieren (alle Länder)') }}
            </flux:button>
            <flux:button
                align="start"
                class="cursor-pointer min-h-11 !h-auto w-full !whitespace-normal py-2"
                x-copy-to-clipboard="buildUrl(true)"
                data-testid="{{ $testIdPrefix }}-copy-scoped"
            >
                <span x-text="scopedCopyLabel"></span>
            </flux:button>
        </div>
    </flux:popover>
</flux:dropdown>
