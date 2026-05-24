<?php

use Stefro\LaravelLangCountry\LangCountry;

/**
 * Resolves a lang-country data file the same way the package does:
 * a published override in lang/lang-country-overrides/ takes precedence,
 * otherwise the file shipped with the package is used.
 */
function resolveLangCountryFile(string $langCountry): ?string
{
    $override = lang_path('lang-country-overrides/'.$langCountry.'.json');
    if (file_exists($override)) {
        return $override;
    }

    $packaged = base_path('vendor/stefro/laravel-lang-country/src/LangCountryData/'.$langCountry.'.json');

    return file_exists($packaged) ? $packaged : null;
}

dataset('allowed_lang_countries', function () {
    $config = require __DIR__.'/../../config/lang-country.php';

    return collect($config['allowed'])
        ->mapWithKeys(fn (string $langCountry) => [$langCountry => [$langCountry]])
        ->all();
});

it('has a resolvable data file for every allowed lang-country', function (string $langCountry) {
    $file = resolveLangCountryFile($langCountry);

    expect($file)->not->toBeNull(
        "No data file found for allowed lang-country [{$langCountry}]. "
        ."Add lang/lang-country-overrides/{$langCountry}.json."
    );

    $data = json_decode(file_get_contents($file), true);

    expect($data)->toBeArray()->toHaveKeys([
        'country',
        'country_name',
        'country_name_local',
        'lang',
        'name',
        'date_numbers',
        'date_numbers_full_capitals',
        'date_words_without_day',
        'date_words_with_day',
        'date_birthday',
        'time_format',
        'emoji_flag',
        'currency_code',
        'currency_symbol',
        'currency_symbol_local',
        'currency_name',
        'currency_name_local',
    ]);
})->with('allowed_lang_countries');

it('can construct LangCountry for every allowed lang-country without error', function (string $langCountry) {
    session(['lang_country' => $langCountry]);

    $langCountryInstance = new LangCountry;

    expect($langCountryInstance->currentLangCountry())->toBe($langCountry);
})->with('allowed_lang_countries');

it('switches away from a freshly added lang-country without throwing', function () {
    session(['lang_country' => 'lv-LV']);

    $response = $this->get(config('lang-country.lang_switcher_uri').'/de-DE');

    $response->assertRedirect();
    expect(session('lang_country'))->toBe('de-DE');
});
