<?php

use function Livewire\Volt\{state, computed};

state(['langCountry' => fn() => session('lang_country', config('lang-country.fallback'))]);

$languages = computed(function () {
    // Scan lang folder for available languages
    $availableLanguages = collect(glob(base_path('lang/*.json')))
        ->map(fn($file) => pathinfo($file, PATHINFO_FILENAME))
        ->toArray();

    $allLanguages = [
        'de' => ['name' => 'Deutsch', 'countries' => ['de-DE', 'de-AT', 'de-CH']],
        'en' => ['name' => 'English', 'countries' => ['en-GB', 'en-US', 'en-AU', 'en-CA']],
        'es' => ['name' => 'Español', 'countries' => ['es-ES', 'es-CL', 'es-CO']],
        'hu' => ['name' => 'Magyar', 'countries' => ['hu-HU']],
        'nl' => ['name' => 'Nederlands', 'countries' => ['nl-NL', 'nl-BE']],
        'pl' => ['name' => 'Polski', 'countries' => ['pl-PL']],
        'pt' => ['name' => 'Português', 'countries' => ['pt-PT']],
    ];

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
});

$updateLanguage = function () {
    return redirect()->route('lang_country.switch', ['lang_country' => $this->langCountry]);
};

?>

<div>
    <flux:select
        variant="listbox" searchable
        wire:model.live="langCountry"
        wire:change="updateLanguage"
        :placeholder="__('Sprache wählen')"
    >
        @foreach($this->languages as $option)
            <flux:select.option value="{{ $option['value'] }}">
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
