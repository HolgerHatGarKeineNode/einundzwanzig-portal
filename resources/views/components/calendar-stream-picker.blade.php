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

<flux:dropdown
    position="bottom"
    align="start"
    x-data="{
        country: {!! \Illuminate\Support\Js::from($defaultCountry)->toHtml() !!},
        language: {!! \Illuminate\Support\Js::from($defaultLanguage)->toHtml() !!},
        timezone: {!! \Illuminate\Support\Js::from($defaultTimezone)->toHtml() !!},
        baseUrl: {!! \Illuminate\Support\Js::from($baseUrl)->toHtml() !!},
        get triggerLabel() {
            return this.language.toUpperCase() + ' · ' + this.country.toUpperCase() + ' · ' + this.timezone;
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
    <flux:button class="cursor-pointer min-h-11" icon="calendar-date-range" data-testid="{{ $testIdPrefix }}-trigger">
        <span x-text="triggerLabel"></span>
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
                 und Zeitzone zu setzen. --}}
            <flux:button
                class="cursor-pointer min-h-11"
                x-copy-to-clipboard="buildUrl(false)"
                data-testid="{{ $testIdPrefix }}-copy-all"
            >
                {{ __('Alle Meetup Events kopieren') }}
            </flux:button>
            <flux:button
                class="cursor-pointer min-h-11"
                x-copy-to-clipboard="buildUrl(true)"
                data-testid="{{ $testIdPrefix }}-copy-scoped"
            >
                {{ __('Nur Meetup Events des gewählten Landes kopieren') }}
            </flux:button>
        </div>
    </flux:popover>
</flux:dropdown>
