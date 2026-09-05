<?php

use App\Support\LocationTimezone;

/**
 * The nine entries the deleted `CountryTimezone::MAP` held, pinned at a representative
 * city of each country (issue #104, definition of done 2).
 *
 * `at` and `ch` CHANGE, and that is the correction, not a regression: the old map sent
 * both to Europe/Berlin, which has the same UTC offset as Europe/Vienna and
 * Europe/Zurich but is not their identifier. A client showing the zone name showed the
 * wrong country.
 */
it('resolves every country the old country map covered', function (string $code, float $latitude, float $longitude, string $expected) {
    expect(LocationTimezone::forLocation($code, $latitude, $longitude))->toBe($expected);
})->with([
    'de — Berlin' => ['de', 52.5200, 13.4050, 'Europe/Berlin'],
    'at — Wien (was Europe/Berlin)' => ['at', 48.2082, 16.3738, 'Europe/Vienna'],
    'ch — Zürich (was Europe/Berlin)' => ['ch', 47.3769, 8.5417, 'Europe/Zurich'],
    'nl — Amsterdam' => ['nl', 52.3676, 4.9041, 'Europe/Amsterdam'],
    'hu — Budapest' => ['hu', 47.4979, 19.0402, 'Europe/Budapest'],
    'pl — Warszawa' => ['pl', 52.2297, 21.0122, 'Europe/Warsaw'],
    'es — Madrid' => ['es', 40.4168, -3.7038, 'Europe/Madrid'],
    'pt — Lisboa' => ['pt', 38.7223, -9.1393, 'Europe/Lisbon'],
    'lv — Rīga' => ['lv', 56.9496, 24.1052, 'Europe/Riga'],
]);

/**
 * The reporter's own coordinates and the rest of the brief's table (issue #104).
 */
it('resolves a location inside a country that spans several zones', function (string $code, float $latitude, float $longitude, string $expected) {
    expect(LocationTimezone::forLocation($code, $latitude, $longitude))->toBe($expected);
})->with([
    // Union Jack Pub, Broad Ripple Avenue, Indianapolis — the venue from issue #104.
    'us — Indianapolis' => ['us', 39.7684, -86.1581, 'America/Indiana/Indianapolis'],
    'us — New York' => ['us', 40.7128, -74.0060, 'America/New_York'],
    'us — Los Angeles' => ['us', 34.0522, -118.2437, 'America/Los_Angeles'],
    'us — Honolulu' => ['us', 21.3069, -157.8583, 'Pacific/Honolulu'],
    'es — Las Palmas' => ['es', 28.1235, -15.4363, 'Atlantic/Canary'],
    'pt — Funchal' => ['pt', 32.6600, -16.9200, 'Atlantic/Madeira'],
]);

it('accepts the country code in any case, as countries.code is stored in both', function () {
    expect(LocationTimezone::forLocation('US', 39.7684, -86.1581))->toBe('America/Indiana/Indianapolis')
        ->and(LocationTimezone::forLocation('us', 39.7684, -86.1581))->toBe('America/Indiana/Indianapolis')
        ->and(LocationTimezone::forLocation(' Us ', 39.7684, -86.1581))->toBe('America/Indiana/Indianapolis');
});

it('accepts the decimal-cast strings Eloquent hands back for coordinates', function () {
    expect(LocationTimezone::forLocation('us', '39.7684000', '-86.1581000'))
        ->toBe('America/Indiana/Indianapolis');
});

/**
 * A country whose tzdata list holds exactly one zone needs no coordinate at all. This
 * is what keeps AT/CH/NL/HU/PL/LV — and Germany, once Europe/Busingen is excluded —
 * resolving for a record whose city relation is missing.
 */
it('resolves a single-zone country without any coordinate', function (string $code, string $expected) {
    expect(LocationTimezone::forLocation($code, null, null))->toBe($expected);
})->with([
    ['de', 'Europe/Berlin'],
    ['at', 'Europe/Vienna'],
    ['ch', 'Europe/Zurich'],
    ['nl', 'Europe/Amsterdam'],
    ['hu', 'Europe/Budapest'],
    ['pl', 'Europe/Warsaw'],
    ['lv', 'Europe/Riga'],
]);

it('returns null rather than guessing when a multi-zone country has no coordinate', function () {
    expect(LocationTimezone::forLocation('us', null, null))->toBeNull()
        ->and(LocationTimezone::forLocation('es', null, null))->toBeNull()
        ->and(LocationTimezone::forLocation('us', 39.7684, null))->toBeNull()
        ->and(LocationTimezone::forLocation('us', 'not-a-number', -86.1581))->toBeNull();
});

it('returns null for a country code tzdata does not know', function () {
    expect(LocationTimezone::forLocation('zz', 0.0, 0.0))->toBeNull();
});

/**
 * DateTimeZone::listIdentifiers() THROWS a ValueError with PER_COUNTRY when the code is
 * not exactly two letters — the empty string a missing country produces included. An
 * unguarded call would take the whole publisher down with an uncaught error instead of
 * dropping one optional tag.
 */
it('returns null instead of throwing for a malformed country code', function (?string $code) {
    expect(LocationTimezone::forLocation($code, 52.5200, 13.4050))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'one letter' => ['D'],
    'three letters' => ['DEU'],
    'digits' => ['12'],
    'punctuation' => ['d-'],
]);

/**
 * Regression guard for the pathology that made the plain nearest-point algorithm
 * unusable as delivered: Europe/Busingen is a 7.6 km² German exclave inside
 * Switzerland, and its representative point beats Europe/Berlin for 22 of Germany's 32
 * largest cities. Removing `Europe/Busingen` from EXCLAVE_ZONES turns every case below
 * red.
 */
it('keeps German cities on Europe/Berlin instead of the Büsingen exclave', function (string $city, float $latitude, float $longitude) {
    expect(LocationTimezone::forLocation('de', $latitude, $longitude))->toBe('Europe/Berlin');
})->with([
    ['München', 48.1351, 11.5820],
    ['Köln', 50.9375, 6.9603],
    ['Frankfurt am Main', 50.1109, 8.6821],
    ['Stuttgart', 48.7758, 9.1829],
    ['Nürnberg', 49.4521, 11.0767],
    ['Freiburg im Breisgau', 47.9990, 7.8421],
    // 37 km from the Büsingen point — the closest large German city to it.
    ['Konstanz', 47.6603, 9.1758],
]);

/**
 * The same pathology in Spain: Africa/Ceuta's point sits in North Africa and beats
 * Europe/Madrid for all of Andalusia. Removing `Africa/Ceuta` from EXCLAVE_ZONES turns
 * every case below red.
 */
it('keeps Andalusian cities on Europe/Madrid instead of the Ceuta exclave', function (string $city, float $latitude, float $longitude) {
    expect(LocationTimezone::forLocation('es', $latitude, $longitude))->toBe('Europe/Madrid');
})->with([
    ['Sevilla', 37.3891, -5.9845],
    ['Málaga', 36.7213, -4.4214],
    ['Cádiz', 36.5271, -6.2886],
    ['Granada', 37.1773, -3.5986],
    ['Almería', 36.8340, -2.4637],
    // 31 km from the Ceuta point across the strait.
    ['Algeciras', 36.1408, -5.4562],
]);

/**
 * The property that makes the exclusion safe rather than merely convenient, asserted
 * against tzdata instead of against the docblock that claims it.
 *
 * Each excluded zone must have IDENTICAL transition rules to the zone its country
 * resolves to at that same point, over a window around today. As long as that holds,
 * dropping the zone cannot change the wall clock any client renders — it only changes
 * which of two interchangeable identifiers is published. The day a tzdata release
 * splits them, this fails and the exclusion has to be reconsidered, rather than
 * quietly shifting a published event by an hour.
 */
it('excludes only zones that are rule-identical to their country fallback', function () {
    $from = strtotime('-2 years');
    $to = strtotime('+5 years');

    $signature = function (string $identifier) use ($from, $to): string {
        return collect((new DateTimeZone($identifier))->getTransitions($from, $to))
            ->map(fn (array $transition): string => $transition['ts'].':'.$transition['offset'].':'.($transition['isdst'] ? 1 : 0))
            ->implode('|');
    };

    expect(LocationTimezone::excludedZones())->not->toBeEmpty();

    foreach (LocationTimezone::excludedZones() as $excluded) {
        $location = (new DateTimeZone($excluded))->getLocation();

        expect($location)->not->toBeFalse("tzdata no longer knows a location for {$excluded}");

        $fallback = LocationTimezone::forLocation(
            $location['country_code'],
            $location['latitude'],
            $location['longitude'],
        );

        expect($fallback)->not->toBeNull("no fallback zone remains for {$excluded}")
            ->and($fallback)->not->toBe($excluded)
            ->and($signature($fallback))->toBe(
                $signature($excluded),
                "{$excluded} and its fallback {$fallback} no longer share their transition rules — excluding it would now move published events"
            );
    }
});
