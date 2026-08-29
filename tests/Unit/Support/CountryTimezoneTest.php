<?php

use App\Support\CountryTimezone;

it('maps known country codes to their IANA timezone', function (string $code, string $expected) {
    expect(CountryTimezone::forCountryCode($code))->toBe($expected);
})->with([
    ['de', 'Europe/Berlin'],
    ['at', 'Europe/Berlin'],
    ['ch', 'Europe/Berlin'],
    ['nl', 'Europe/Amsterdam'],
    ['hu', 'Europe/Budapest'],
    ['pl', 'Europe/Warsaw'],
    ['es', 'Europe/Madrid'],
    ['pt', 'Europe/Lisbon'],
    ['lv', 'Europe/Riga'],
]);

it('falls back to Europe/Berlin for unknown or missing country codes', function () {
    expect(CountryTimezone::forCountryCode('xx'))->toBe('Europe/Berlin');
    expect(CountryTimezone::forCountryCode(null))->toBe('Europe/Berlin');
});

it('produces a valid IANA timezone identifier for every mapped code', function (string $expected) {
    expect(in_array($expected, timezone_identifiers_list(), true))->toBeTrue();
})->with([
    'Europe/Berlin',
    'Europe/Amsterdam',
    'Europe/Budapest',
    'Europe/Warsaw',
    'Europe/Madrid',
    'Europe/Lisbon',
    'Europe/Riga',
]);
