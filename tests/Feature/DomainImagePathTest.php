<?php

/*
 * domain_image_path() ist die einzige Quelle fuer das Motiv einer
 * Sprachfassung — vorher lag dieselbe Auswahl dreimal kopiert
 * (get_domain_attributes(), auth/login, auth/mobile-login) und der
 * Rueckfall war dort ueberall 'de-DE.jpg'. Der Kern dieser Datei ist die
 * Regression: eine Fassung ohne eigenes Motiv bekommt jetzt TWENTY ONE,
 * nicht mehr das deutsche Bild.
 */

it('uses the session lang_country when no argument is given', function () {
    session(['lang_country' => 'de-DE']);

    expect(domain_image_path())->toBe('img/domains/de-DE.jpg');
});

it('defaults to de-DE when neither an argument nor a session value exists', function () {
    session()->forget('lang_country');

    expect(domain_image_path())->toBe('img/domains/de-DE.jpg');
});

it('lets an explicit argument override the session value', function () {
    session(['lang_country' => 'de-DE']);

    expect(domain_image_path('hu-HU'))->toBe('img/domains/hu-HU.jpg');
});

it('gives spanish-speaking latin america the shared lat.png ahead of the jpg check', function (string $langCountry) {
    // Steht bewusst vor der jpg-Existenzpruefung: lat.png ist kein .jpg, eine
    // vertauschte Reihenfolge wuerde diesen Zweig auf den Default zurueckwerfen.
    expect(domain_image_path($langCountry))->toBe('img/domains/lat.png');
})->with(['es-CL', 'es-CO']);

it('keeps its own motif for the four countries that have one', function (string $langCountry, string $expected) {
    expect(domain_image_path($langCountry))->toBe($expected);
})->with([
    'de-DE' => ['de-DE', 'img/domains/de-DE.jpg'],
    'hu-HU' => ['hu-HU', 'img/domains/hu-HU.jpg'],
    'nl-NL' => ['nl-NL', 'img/domains/nl-NL.jpg'],
    'pl-PL' => ['pl-PL', 'img/domains/pl-PL.jpg'],
]);

it('falls back to the twenty-one image for language versions without their own motif', function (string $langCountry) {
    expect(domain_image_path($langCountry))->toBe('img/domains/twenty-one.png');
})->with([
    'englisch (GB)' => ['en-GB'],
    'englisch (US)' => ['en-US'],
    'englisch (CA)' => ['en-CA'],
    'franzoesisch' => ['fr-FR'],
    'tschechisch' => ['cs-CZ'],
    'unbekannter code' => ['xx-YY'],
]);

it('regression: no longer falls back to the german image for a language version without its own motif', function (string $langCountry) {
    // Das war der eigentliche Fehler: bis 2026-08-25 fiel Stufe 3 hart auf
    // 'de-DE.jpg' — jede Fassung ohne eigenes Motiv bekam damit das deutsche
    // Bild, sichtbar im Kopfbereich, in der Social-Media-Vorschau und im
    // Login-QR-Code.
    expect(domain_image_path($langCountry))->not->toBe('img/domains/de-DE.jpg');
})->with(['en-US', 'en-GB', 'en-CA', 'fr-FR', 'cs-CZ']);
