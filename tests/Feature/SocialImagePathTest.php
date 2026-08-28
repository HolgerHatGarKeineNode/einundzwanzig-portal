<?php

/*
 * social_image_path() ist die Bildauswahl fuer die Social-Media-Vorschau
 * (og:image), ein eigener Pfad NEBEN domain_image_path() (siehe
 * DomainImagePathTest.php). Drei Stufen, in dieser Reihenfolge:
 *
 *   1. img/social/<lang-COUNTRY>.png — ein Bild fuer genau eine Fassung.
 *   2. img/social/<lang>.png — eines fuer die ganze Sprache (der Regelfall:
 *      en.png gilt fuer alle englischen Laendervarianten).
 *   3. domain_image_path($langCountry) — unveraendertes Verhalten fuer jede
 *      Fassung ohne eigenes Vorschaubild.
 *
 * Stufe 1 hat im Repo aktuell KEIN Blatt (nur public/img/social/en.png fuer
 * Stufe 2 existiert). Sie wird hier ueber eine TEMPORAERE PNG-Datei
 * geprueft, die pro Test entsteht und in einem finally-Block wieder
 * verschwindet — kein Asset landet im Repo, git bleibt sauber.
 */

function tempSocialImagePath(string $langCountry): string
{
    return public_path('img/social/'.$langCountry.'.png');
}

function withTempSocialImage(string $langCountry, int $width, int $height, callable $callback): void
{
    $path = tempSocialImagePath($langCountry);

    // Ein echtes, gueltiges PNG: ImageMeta und getimagesize() ruehren daran,
    // ein erfundener Byte-String wuerde dort mit false scheitern.
    $image = imagecreatetruecolor($width, $height);
    imagepng($image, $path);
    imagedestroy($image);

    try {
        $callback();
    } finally {
        @unlink($path);
    }
}

it('uses the session lang_country when no argument is given', function () {
    session(['lang_country' => 'en-US']);

    expect(social_image_path())->toBe('img/social/en.png');
});

it('shares one 1200x630 image across every english variant — the actual 1,91:1-format-Zusage', function (string $langCountry) {
    // Nicht nur der Dateiname zaehlt: die Groesse ist der eigentliche Vertrag
    // mit der Social-Media-Vorschau (1,91:1 statt der quadratischen
    // domain_image_path()-Motive). Ein Test, der nur auf 'img/social/en.png'
    // prueft, haette einen Tausch gegen ein falsch zugeschnittenes Blatt
    // gleichen Namens nicht bemerkt.
    $path = social_image_path($langCountry);

    expect($path)->toBe('img/social/en.png');

    [$width, $height] = getimagesize(public_path($path));
    expect($width)->toBe(1200)->and($height)->toBe(630);
})->with([
    'en-US' => ['en-US'],
    'en-GB' => ['en-GB'],
    'en-CA' => ['en-CA'],
    'en-AU' => ['en-AU'],
    'en-CH' => ['en-CH'],
]);

it('falls back unchanged to the square domain motif for versions without a dedicated social image', function (string $langCountry, string $expected, int $expectedWidth, int $expectedHeight) {
    $path = social_image_path($langCountry);

    expect($path)->toBe($expected);

    [$width, $height] = getimagesize(public_path($path));
    expect($width)->toBe($expectedWidth)->and($height)->toBe($expectedHeight);
})->with([
    'deutsch' => ['de-DE', 'img/domains/de-DE.jpg', 512, 512],
    'ungarisch' => ['hu-HU', 'img/domains/hu-HU.jpg', 320, 320],
    'lateinamerikanisches spanisch' => ['es-CL', 'img/domains/lat.png', 360, 360],
    'franzoesisch (kein eigenes motiv irgendwo)' => ['fr-FR', 'img/domains/twenty-one.png', 512, 512],
]);

it('prefers a dedicated lang-COUNTRY image over the shared language image', function () {
    // 'en-ZZ' ist eine erfundene Kombination aus einer echten Sprache (die
    // ueber en.png laengst eine Stufe-2-Antwort haette) und einem erfundenen
    // Land — genau der Fall, an dem eine vertauschte Stufenreihenfolge
    // sichtbar wuerde.
    withTempSocialImage('en-ZZ', 1080, 1080, function () {
        expect(social_image_path('en-ZZ'))->toBe('img/social/en-ZZ.png');
    });

    // Nach dem Aufraeumen faellt dieselbe Kombination auf Stufe 2 zurueck.
    expect(social_image_path('en-ZZ'))->toBe('img/social/en.png');
});

it('picks up a dedicated lang-COUNTRY image even for a language with no language-level image at all', function () {
    withTempSocialImage('zz-ZZ', 900, 900, function () {
        expect(social_image_path('zz-ZZ'))->toBe('img/social/zz-ZZ.png');
    });

    expect(social_image_path('zz-ZZ'))->toBe('img/domains/twenty-one.png');
});

it('forwards the explicit argument, not the session value, into the domain_image_path fallback', function () {
    // Stufe 3 reicht den Parameter durch — vertauscht mit einem Aufruf ohne
    // Argument (der still auf die Session zurueckfiele), wuerde dieser Test
    // bei abweichender Session rot.
    session(['lang_country' => 'de-DE']);

    expect(social_image_path('hu-HU'))->toBe('img/domains/hu-HU.jpg');
});
