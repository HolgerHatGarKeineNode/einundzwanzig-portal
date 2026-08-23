<?php

use Livewire\Component;

new class extends Component {
    public $langCountry;

    public function mount() {
        $this->langCountry = session('lang_country', config('lang-country.fallback'));
    }

    public function getLanguagesProperty() {
        // Scan lang folder for available languages
        $availableLanguages = collect(glob(base_path('lang/*.json')))
            ->map(fn($file) => pathinfo($file, PATHINFO_FILENAME))
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
                    'label' => $langData['name'] . ' (' . strtoupper($countryCode) . ')',
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
