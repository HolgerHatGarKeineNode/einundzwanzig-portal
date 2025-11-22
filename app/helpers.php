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
