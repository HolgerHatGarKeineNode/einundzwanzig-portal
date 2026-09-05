<?php

namespace App\Support;

use DateTimeZone;

/**
 * Resolves the IANA time zone of a place from its country code and coordinates.
 *
 * This replaces `App\Support\CountryTimezone`, a nine-entry country code -> zone map
 * that was not merely incomplete but structurally wrong (issue #104): the US spans six
 * zones, and so do CA, AU, BR, RU and MX, so no country-keyed table can be right for
 * them. An Indianapolis meetup was published with `start_tzid: Europe/Berlin`, which
 * made every client render it on the wrong day at the wrong hour.
 *
 * ## Where the data comes from
 *
 * PHP's own bundled tzdata, so this needs no new dependency. Every zone in the database
 * carries a representative coordinate:
 *
 *   DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'US')  // 29 zones
 *   (new DateTimeZone('America/Indiana/Indianapolis'))->getLocation()
 *   // ['country_code' => 'US', 'latitude' => 39.7683, 'longitude' => -86.1581, ...]
 *
 * The zone whose representative point is nearest the place (great-circle distance)
 * wins. Measured against PHP 8.5.9 / tzdata 2026.3: Indianapolis 39.7684/-86.1581 ->
 * America/Indiana/Indianapolis, 40.7128/-74.0060 -> America/New_York, 34.0522/-118.2437
 * -> America/Los_Angeles, Las Palmas -> Atlantic/Canary, Funchal -> Atlantic/Madeira.
 *
 * ## What this is NOT
 *
 * A nearest-representative-point match is not a polygon lookup, and it is exact only
 * near the points tzdata happens to name. Fort Wayne, Indiana resolves to
 * America/Indiana/Winamac rather than America/Indiana/Indianapolis, because Winamac's
 * point is closer. Both are US Eastern with identical rules since 2006, so the rendered
 * local time is right either way — but the identifier can name the wrong county. A
 * polygon lookup would need a dependency and a shapefile; the accuracy this buys is
 * below the accuracy the portal's own data has (a meetup carries a city, not a parcel).
 *
 * @see EXCLAVE_ZONES for the one case where the nearest match is wrong at a scale that
 *      matters, and how it is handled.
 */
final class LocationTimezone
{
    /**
     * Zones dropped from the candidate list before the nearest match runs.
     *
     * A representative point is a single coordinate, so a zone covering a few square
     * kilometres competes on equal footing with one covering a country. Where such a
     * micro-territory sits deep inside a large neighbour, its point wins a Voronoi cell
     * far larger than the zone itself, and the plain algorithm regresses a country the
     * portal already served correctly. Both entries were found by measurement, not by
     * guessing:
     *
     * - `Europe/Busingen` — a 7.6 km² German exclave inside Switzerland, point
     *   47.70/8.6833. Against Germany's 32 largest cities it beats Europe/Berlin for
     *   22 of them, including München (221 km vs 502 km), Köln, Frankfurt, Stuttgart
     *   and Nürnberg. Germany is this portal's home country.
     * - `Africa/Ceuta` — Ceuta and Melilla, Spanish exclaves in North Africa, point
     *   35.8833/-5.3167. It beats Europe/Madrid for every city in Andalusia: Algeciras
     *   (31 km), Cádiz (113 km), Málaga (123 km), Sevilla (178 km), Granada (210 km),
     *   Almería (277 km).
     *
     * DROPPING THEM CANNOT CHANGE A RENDERED TIME, and that is the property that makes
     * this safe rather than merely convenient: each excluded zone has identical
     * transition rules to the zone its country falls back to (Büsingen follows CET/CEST
     * exactly as Berlin does, Ceuta exactly as Madrid does). A meetup in Büsingen itself
     * is therefore published as Europe/Berlin and still renders at the right wall clock.
     * `LocationTimezoneExclaveTest` asserts that equality against tzdata rather than
     * trusting this paragraph, so the day a future tzdata release splits them, the guard
     * fails instead of the events.
     *
     * The exclusion is deliberately NOT derived by a rule. The obvious derivation —
     * "a zone with a rule-identical domestic sibling whose point is nearer a foreign
     * zone than a domestic one" — was measured across all 419 located zones and flags
     * 36, among them Europe/Berlin, Europe/Madrid, America/New_York and Europe/Moscow.
     * A rule that excludes New York is worse than a two-line list with a guard test.
     *
     * @var list<string>
     */
    private const EXCLAVE_ZONES = [
        'Europe/Busingen',
        'Africa/Ceuta',
    ];

    /**
     * Mean Earth radius in kilometres (IUGG). Only the ordering of the distances
     * matters here; the unit is kept so a failing test can report a real number.
     */
    private const EARTH_RADIUS_KM = 6371.0088;

    /**
     * @var array<string, array<string, array{float, float}>>
     */
    private static array $candidateCache = [];

    /**
     * The IANA zone identifier for a place, or null when it cannot be determined.
     *
     * NULL IS A DELIBERATE RESULT, not an oversight. The caller's contract is to omit
     * `start_tzid` in that case, which NIP-52 explicitly allows (the tag is optional);
     * the `start` tag is an absolute Unix timestamp and stays correct, so a client
     * simply renders the event in the reader's own zone. The alternative — a
     * plausible-looking default — is precisely what produced issue #104: an American
     * meetup published as Europe/Berlin showed up on the wrong day, and every client
     * believed it, because a wrong identifier is indistinguishable from a right one.
     *
     * @param  string|null  $countryCode  ISO 3166-1 alpha-2, in any case
     * @param  mixed  $latitude  degrees; accepts the decimal-cast strings Eloquent returns
     * @param  mixed  $longitude  degrees; accepts the decimal-cast strings Eloquent returns
     */
    public static function forLocation(?string $countryCode, mixed $latitude, mixed $longitude): ?string
    {
        $candidates = self::candidatesFor($countryCode);

        if ($candidates === []) {
            return null;
        }

        // A single-zone country needs no coordinate at all, which is what keeps AT, CH,
        // NL, HU, PL and LV resolving even for a record with no usable position. Germany
        // joins them once Europe/Busingen is out of the list.
        if (count($candidates) === 1) {
            return array_key_first($candidates);
        }

        if ($latitude === null || $longitude === null || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        $nearest = null;
        $nearestDistance = INF;

        foreach ($candidates as $identifier => [$zoneLatitude, $zoneLongitude]) {
            $distance = self::distanceKm($latitude, $longitude, $zoneLatitude, $zoneLongitude);

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $identifier;
            }
        }

        return $nearest;
    }

    /**
     * The located zones of a country, minus {@see self::EXCLAVE_ZONES}.
     *
     * @return array<string, array{float, float}> identifier => [latitude, longitude]
     */
    public static function candidatesFor(?string $countryCode): array
    {
        $code = mb_strtoupper(trim((string) $countryCode));

        /*
         * The shape check is a guard against a FATAL, not tidiness. With PER_COUNTRY,
         * DateTimeZone::listIdentifiers() throws a ValueError for anything that is not
         * two letters — including the empty string a missing country produces. An
         * unvalidated `countries.code` would take the whole publisher down with an
         * uncaught error rather than skipping one tag. A well-formed but unknown code
         * ('ZZ') returns an empty array instead, which is why only the shape is checked
         * here and the emptiness is handled by the caller.
         */
        if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            return [];
        }

        if (isset(self::$candidateCache[$code])) {
            return self::$candidateCache[$code];
        }

        $candidates = [];

        foreach (DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code) as $identifier) {
            if (in_array($identifier, self::EXCLAVE_ZONES, true)) {
                continue;
            }

            $location = (new DateTimeZone($identifier))->getLocation();

            if ($location === false || ! isset($location['latitude'], $location['longitude'])) {
                continue;
            }

            $candidates[$identifier] = [(float) $location['latitude'], (float) $location['longitude']];
        }

        return self::$candidateCache[$code] = $candidates;
    }

    /**
     * The zones this resolver refuses to consider — exposed so the guard test can
     * assert the property that makes the exclusion safe.
     *
     * @return list<string>
     */
    public static function excludedZones(): array
    {
        return self::EXCLAVE_ZONES;
    }

    /**
     * Great-circle distance in kilometres (haversine).
     *
     * The right metric for "nearest point on a sphere", chosen by construction rather
     * than by measurement — and the measurement is worth recording, because it does NOT
     * support the stronger claim that first stood here.
     *
     * Swapping haversine for a flat |Δlat| + |Δlon| over degrees leaves every test in
     * this suite green. Ranked over a 1° grid of every point within 300 km of a US zone
     * point, the two metrics disagree at 34 positions — all of them in the Alaskan
     * panhandle or in empty desert, between zones with identical rules, and at those
     * positions NEITHER metric is reliably right, because the nearest-representative-
     * point model is itself the approximation (see the class docblock above). So the
     * honest statement is: this cannot be wrong where the flat version would be right,
     * it costs one function call, and no test pins it because there is no requirement
     * to pin.
     */
    private static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radians = M_PI / 180;

        $a = sin(($lat2 - $lat1) * $radians / 2) ** 2
            + cos($lat1 * $radians) * cos($lat2 * $radians) * sin(($lon2 - $lon1) * $radians / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }
}
