<?php

if (!function_exists('route_with_country')) {
    function route_with_country(string $name, array $parameters = [], bool $absolute = true): string
    {
        if (!isset($parameters['country'])) {
            $country = request()->route('country') ?? 'de';
        } else {
            $country = str(session('lang_country', 'de'))->after('-')->lower();
        }
        $parameters = ['country' => $country] + $parameters;

        return route($name, $parameters, $absolute);
    }
}

if (!function_exists('get_domain_image')) {
    function get_domain_image(): string
    {
        $langCountry = session('lang_country', 'de-DE');

        // Check if specific domain image exists
        if (!file_exists(public_path('img/domains/'.$langCountry.'.jpg'))) {
            $langCountry = 'de-DE';
        }

        $southAmericanCountries = [
            'ar-AR', // Argentina
            'bo-BO', // Bolivia
            'br-BR', // Brazil
            'cl-CL', // Chile
            'co-CO', // Colombia
            'ec-EC', // Ecuador
            'gy-GY', // Guyana
            'py-PY', // Paraguay
            'pe-PE', // Peru
            'sr-SR', // Suriname
            'uy-UY', // Uruguay
            've-VE', // Venezuela
        ];

        // Use LAT image for South American countries
        if (in_array($langCountry, $southAmericanCountries, true)) {
            return asset('img/domains/lat.png');
        }

        return asset('img/domains/'.$langCountry.'.jpg');
    }
}
