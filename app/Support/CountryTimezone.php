<?php

namespace App\Support;

/**
 * Laendercode -> IANA-Zeitzone fuer den `start_tzid`-Tag aus NIP-52 (kind 31923).
 *
 * Dieselben Werte wie `PublishUnpublishedItems::TZ_MAP` (kind:1-Textnote-Publisher),
 * bewusst als eigene Kopie statt einer gemeinsamen Extraktion: jene Datei hat keine
 * Testabdeckung, und ein Refactor daran ist nicht Teil dieses NIP-52-Umfangs.
 */
class CountryTimezone
{
    private const MAP = [
        'de' => 'Europe/Berlin',
        'at' => 'Europe/Berlin',
        'ch' => 'Europe/Berlin',
        'nl' => 'Europe/Amsterdam',
        'hu' => 'Europe/Budapest',
        'pl' => 'Europe/Warsaw',
        'es' => 'Europe/Madrid',
        'pt' => 'Europe/Lisbon',
        'lv' => 'Europe/Riga',
    ];

    public static function forCountryCode(?string $countryCode): string
    {
        // PHP 8.5 deprecates null as an array offset — the empty string never
        // matches a key, so this stays a one-liner without triggering it.
        return self::MAP[$countryCode ?? ''] ?? 'Europe/Berlin';
    }
}
