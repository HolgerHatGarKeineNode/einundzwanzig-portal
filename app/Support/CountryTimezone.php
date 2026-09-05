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
        //
        // Lowercased here, at the point of use (issue #76): the keys above are
        // lowercase, a PHP array lookup is case-sensitive, and the stored
        // `countries.code` carries whichever case its writer used — CountryFactory
        // writes 'ES'. The case-insensitive Country::matchingCode() scope added for
        // #58 compares in SQL and therefore never reaches this lookup, so without
        // the normalisation a Spanish meetup is published with Europe/Berlin.
        return self::MAP[mb_strtolower($countryCode ?? '')] ?? 'Europe/Berlin';
    }
}
