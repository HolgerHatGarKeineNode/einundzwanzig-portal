<?php

use App\Models\Meetup;

/*
 * The sidebar lists the meetups a user leads, and most names repeat the brand:
 * "Einundzwanzig Hamburg", "EINUNDZWANZIG WÜRZBURG". The stored name keeps the
 * prefix; only the display drops it.
 */

it('drops a leading Einundzwanzig from the display name', function (string $stored, string $shown) {
    expect(Meetup::factory()->make(['name' => $stored])->short_name)->toBe($shown);
})->with([
    'title case' => ['Einundzwanzig Hamburg', 'Hamburg'],
    'upper case' => ['EINUNDZWANZIG WÜRZBURG', 'WÜRZBURG'],
    'hyphen' => ['Einundzwanzig-Konstanz', 'Konstanz'],
    'colon and spaces' => ['Einundzwanzig :  Lübeck', 'Lübeck'],
]);

it('keeps names the prefix does not lead or would leave empty', function (string $stored) {
    expect(Meetup::factory()->make(['name' => $stored])->short_name)->toBe($stored);
})->with([
    'no prefix' => ['BitcoinWalk Augsburg'],
    'prefix inside a word' => ['Einundzwanzigste Runde'],
    'digits' => ['21Neumarkt'],
    'prefix only' => ['Einundzwanzig'],
    'prefix not leading' => ['Orange Lounge Einundzwanzig'],
]);
