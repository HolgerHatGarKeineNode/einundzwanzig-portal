<?php

use Livewire\Component;

new class extends Component
{
    public $langCountry;

    public function mount()
    {
        $this->langCountry = $this->offeredLangCountry(
            (string) session('lang_country', config('lang-country.fallback'))
        );
    }

    /**
     * Map the session locale onto the option set this select actually renders.
     *
     * The two sides are deliberately NOT the same set. `config('lang-country.allowed')`
     * lists 33 locales that may legitimately sit in `session('lang_country')` — the
     * Accept-Language guess in DomainMiddleware and the package's own login listener both
     * produce them — while the options below are limited to languages that have a
     * `lang/*.json` file, 17 today. The other 16 (fr-*, it-*, ru-RU, da-DA, ...) are
     * locales the portal cannot switch its interface to, so widening the options would
     * offer translations that do not exist. Narrowing `allowed` would take away locale
     * formats that do work.
     *
     * So this side gives way: a value with no matching <ui-option> is never bound. Flux'
     * `ui-selected` element throws `Could not find option for value "…"` while rendering
     * one (issue #73), and because the select sits in the sidebar that hits every page —
     * measured on the meetup list, the meetup page and the event page, one uncaught error
     * per page load.
     *
     * The match is case-insensitive and answers with the OFFERED spelling, which is the
     * half of #73 that was actually reported: a session still carrying `de-de` now selects
     * `de-DE` instead of throwing. Casing is the one difference that names the same locale,
     * so correcting it is not a guess.
     *
     * No match at all means no selection: the placeholder is the honest answer for a
     * locale this portal cannot offer, and `updatedLangCountry()` already treats an empty
     * selection as "nothing chosen", so nothing redirects off the back of it.
     */
    private function offeredLangCountry(string $langCountry): ?string
    {
        foreach ($this->languages as $option) {
            if (mb_strtolower($option['value']) === mb_strtolower($langCountry)) {
                return $option['value'];
            }
        }

        return null;
    }

    public function getLanguagesProperty()
    {
        // Scan lang folder for available languages
        $availableLanguages = collect(glob(base_path('lang/*.json')))
            ->map(fn ($file) => pathinfo($file, PATHINFO_FILENAME))
            ->toArray();

        $allLanguages = config('lang-country.languages');

        // Filter languages based on available JSON files and allowed languages
        $languages = array_filter($allLanguages, function ($data, $key) use ($availableLanguages) {
            return in_array($key, $availableLanguages) &&
                count(array_intersect($data['countries'], config('lang-country.allowed'))) > 0;
        }, ARRAY_FILTER_USE_BOTH);

        // Build options array
        $options = [];
        foreach ($languages as $langCode => $langData) {
            foreach ($langData['countries'] as $langCountry) {
                [$lang, $countryCode] = explode('-', $langCountry);
                $options[] = [
                    'value' => $langCountry,
                    'label' => $langData['name'].' ('.strtoupper($countryCode).')',
                ];
            }
        }

        return $options;
    }

    /**
     * Lifecycle-Hook statt wire:change.
     *
     * Vorher hingen `wire:model.live` und `wire:change="updateLanguage"` am selben
     * Element. Livewire 4 faehrt Property-Updates parallel, also gingen zwei Requests
     * raus: einer, der `langCountry` setzt, und einer, der `updateLanguage()` ruft —
     * letzterer mit dem Snapshot VOR der Aenderung. Traf er zuerst ein, leitete er auf
     * die zuvor gewaehlte Sprache um, und die Auswahl sah aus, als spraenge sie zurueck.
     * Zwei gleichzeitige Updates derselben Komponente sind ausserdem der Weg, auf dem
     * ein Snapshot veraltet und Livewire mit 419 abbricht.
     *
     * Der Hook laeuft im selben Request, in dem die Property gesetzt wird — eine
     * Anfrage, kein Rennen, und der Wert ist garantiert der neue.
     */
    public function updatedLangCountry(): void
    {
        /*
         * Die durchsuchbare flux:select-Listbox sendet beim Leeren der Auswahl ein
         * leeres Array statt eines Strings — kein Sprachwechsel, sondern ein Reset auf
         * "nichts gewaehlt". Ungeprueft in redirectRoute() haette das "Array to string
         * conversion" geworfen, weil der Routen-Parameter dort einen String erwartet.
         */
        if (! is_string($this->langCountry) || $this->langCountry === '') {
            return;
        }

        /*
         * Die bewusste Wahl getrennt festhalten: `lang_country` allein sagt nicht, ob
         * sie jemand getroffen oder eine Middleware aus Accept-Language geraten hat.
         * ApplyChosenLanguageAfterLogin braucht diesen Unterschied.
         */
        session(['lang_country_chosen' => $this->langCountry]);

        $this->redirectRoute('lang_country.switch', ['lang_country' => $this->langCountry]);
    }
};

?>

<div>
    <flux:select
        variant="listbox" searchable
        wire:model.live="langCountry"
        :placeholder="__('Sprache wählen')"
    >
        @foreach($this->languages as $option)
            <flux:select.option value="{{ $option['value'] }}" wire:key="language-option-{{ $loop->index }}">
                <div class="flex items-center space-x-2">
                    <img alt="{{ str($option['value'])->after('-')->lower() }}"
                         src="{{ asset('vendor/blade-flags/country-'.str($option['value'])->after('-')->lower().'.svg') }}"
                         width="24" height="12"/>
                    <span>{{ $option['label'] }}</span>
                </div>
            </flux:select.option>
        @endforeach
    </flux:select>
</div>
