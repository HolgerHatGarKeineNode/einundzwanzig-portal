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

    /*
     * Two properties at once, and they pull against each other.
     *
     * STABLE across renders: on meetups/index.blade.php the component sits next
     * to wire:model.live="search", so every keystroke re-renders it. With the
     * Str::random(8) that used to stand here, every keystroke produced new ids
     * and every selector-based measurement of this component addressed a node
     * that no longer existed.
     *
     * UNIQUE within one page: meetups/landingpage.blade.php renders the
     * component twice (lines 69 and 122), so a fixed literal would make every
     * selector on that page ambiguous.
     *
     * A `static` in this block satisfies neither, and looks like it satisfies
     * both — which is why it is worth naming: Laravel evaluates a view inside a
     * closure literal that is re-created on every single render
     * (Filesystem::getRequire(), framework/src/Illuminate/Filesystem/Filesystem.php:120),
     * so the static is re-initialised each time and the counter never leaves 1.
     * Measured on the landing page before this change: 14 data-testid values,
     * 7 distinct — both instances answered to `calendar-stream-1`.
     *
     * The request is the object with exactly the right lifetime. It is one per
     * render pass, and a Livewire round trip is its own request, so the
     * numbering restarts at the same place and yields the same ids.
     */
    $calendarStreamPickerInstance = (int) request()->attributes->get('calendar-stream-picker-count', 0) + 1;
    request()->attributes->set('calendar-stream-picker-count', $calendarStreamPickerInstance);

    $testIdPrefix = 'calendar-stream-'.$calendarStreamPickerInstance;
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
    {{-- The trigger names the action and nothing else. It used to read
         "DE · DE · Europe/Berlin", which told a visitor nothing about what the
         button opens; the first fix kept those codes as a second line and made
         it worse — in a narrow flex slot (meetups index: between the region
         select and the search field) the label wrapped to five lines and the
         button grew into a block. The codes are redundant anyway: the three
         selects inside the popover show the same values, editable.

         So: one line, Flux' own `whitespace-nowrap` back in force. That is safe
         now because the label is a fixed translated string — the data-driven
         width came from the timezone id (`\DateTimeZone::listIdentifiers()` has
         419 entries, longest 30 characters), and it no longer appears here.
         `min-h-11` keeps the 44px touch target. --}}
    <flux:button
        class="cursor-pointer min-h-11"
        icon="calendar-date-range"
        data-testid="{{ $testIdPrefix }}-trigger"
    >
        {{ __('Kalender abonnieren') }}
    </flux:button>

    <flux:popover class="w-[20rem] max-w-[calc(100vw-2rem)] space-y-4" data-testid="{{ $testIdPrefix }}-panel">
        {{-- Durchsuchbare Listbox statt eines nackten <select>, und zwar nach dem
             Muster, das dieses Repo fuer genau diese drei Listen schon hat:
             country/chooser.blade.php (Flagge + Name) und timezone/chooser.blade.php
             (Suchfeld). \DateTimeZone::listIdentifiers() liefert 419 Eintraege — in
             einem 320px-Popover ist das ohne Suche nicht bedienbar. --}}
        <flux:field>
            <flux:label>{{ __('Land') }}</flux:label>
            <flux:select variant="listbox" searchable x-model="country" class="min-h-11"
                         data-testid="{{ $testIdPrefix }}-country">
                <x-slot name="search">
                    <flux:select.search class="px-4" :placeholder="__('Suche dein Land...')"/>
                </x-slot>
                @foreach($countries as $option)
                    <flux:select.option value="{{ mb_strtolower($option->code) }}">
                        <div class="flex items-center space-x-2">
                            <img alt="{{ mb_strtolower($option->code) }}"
                                 src="{{ asset('vendor/blade-flags/country-'.mb_strtolower($option->code).'.svg') }}"
                                 width="24" height="12"/>
                            <span>{{ $option->name }} ({{ strtoupper($option->code) }})</span>
                        </div>
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Sprache') }}</flux:label>
            <flux:select variant="listbox" searchable x-model="language" class="min-h-11"
                         data-testid="{{ $testIdPrefix }}-language">
                <x-slot name="search">
                    <flux:select.search class="px-4" :placeholder="__('Suche deine Sprache...')"/>
                </x-slot>
                @foreach($supportedLanguages as $code => $data)
                    <flux:select.option value="{{ $code }}">
                        <div class="flex items-center space-x-2">
                            @php
                                // 'countries' listet die Locales dieser Sprache
                                // ("de" => de-DE, de-AT, de-CH). Die erste ist die
                                // Leitregion und liefert die Flagge.
                                $flagCode = mb_strtolower((string) str($data['countries'][0] ?? '')->after('-'));
                            @endphp
                            @if($flagCode !== '')
                                <img alt="{{ $flagCode }}"
                                     src="{{ asset('vendor/blade-flags/country-'.$flagCode.'.svg') }}"
                                     width="24" height="12"/>
                            @endif
                            <span>{{ $data['name'] }}</span>
                        </div>
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Zeitzone') }}</flux:label>
            <flux:select variant="listbox" searchable x-model="timezone" class="min-h-11"
                         data-testid="{{ $testIdPrefix }}-timezone">
                <x-slot name="search">
                    <flux:select.search class="px-4" :placeholder="__('Suche Zeitzone...')"/>
                </x-slot>
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
