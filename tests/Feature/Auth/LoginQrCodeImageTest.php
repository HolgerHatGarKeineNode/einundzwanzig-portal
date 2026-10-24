<?php

use App\Support\LoginQrCode;

/*
 * Beide Login-Seiten bauen ihren QR-Code seit 2026-08-25 ueber
 * domain_image_path() statt ueber eine eigene, hart auf 'de-DE.jpg'
 * fallende Kopie der Auswahl (siehe helpers.php). Diese Tests pruefen die
 * Verdrahtung an genau der Stelle, an der ein Kopie-Rueckfall unbemerkt
 * ueberleben wuerde: nicht ueber die Wortwahl im Code, sondern ueber die
 * tatsaechlich erzeugten QR-Bytes.
 *
 * Verfahren statt Mocking: der Lnurl-Wert steht als Klartext im gerenderten
 * HTML (`href="lightning:{{ $this->lnurl }}"`), also wird er dort
 * ausgelesen und damit unabhaengig derselbe QR-Code neu erzeugt — einmal mit
 * dem erwarteten Motiv, einmal mit dem frueheren de-DE.jpg-Fallback. Die
 * QR-Erzeugung ist deterministisch (gleiche Eingabe -> gleiche Bytes,
 * gemessen), ein Mock der Fassade waere hier reine Verrenkung.
 */
function extractLnurlFromHtml(string $html): string
{
    preg_match('/href="lightning:([^"]+)"/', $html, $matches);

    expect($matches)->toHaveCount(2, 'Konnte den lightning:-Link nicht im gerenderten HTML finden.');

    return $matches[1];
}

function qrCodePngBase64(string $lnurl, string $imagePathRelativeToPublic): string
{
    return base64_encode(LoginQrCode::png($lnurl, public_path($imagePathRelativeToPublic)));
}

it('embeds the twenty-one motif in the desktop login QR code for a language without its own image (regression)', function () {
    session(['lang_country' => 'en-US']);

    $html = $this->get('/login')->assertOk()->getContent();
    $lnurl = extractLnurlFromHtml($html);

    $expectedWithTwentyOne = qrCodePngBase64($lnurl, 'img/domains/twenty-one.png');
    $expectedWithGermanBug = qrCodePngBase64($lnurl, 'img/domains/de-DE.jpg');

    // Das Bild steckt nicht direkt im HTML (es wird als data-URI im <img> der
    // eigentlichen Seite eingebettet), also lesen wir es aus demselben
    // Response-Body wie den Lnurl-Link.
    preg_match('/data:image\/png;base64, ([A-Za-z0-9+\/=]+)"/', $html, $imgMatch);
    expect($imgMatch)->toHaveCount(2, 'Konnte den eingebetteten QR-Code nicht im HTML finden.');
    $actualQrCode = $imgMatch[1];

    expect($actualQrCode)->toBe($expectedWithTwentyOne)
        ->and($actualQrCode)->not->toBe($expectedWithGermanBug);
});

it('keeps embedding the own motif in the desktop login QR code for a language that has one', function () {
    session(['lang_country' => 'hu-HU']);

    $html = $this->get('/login')->assertOk()->getContent();
    $lnurl = extractLnurlFromHtml($html);

    preg_match('/data:image\/png;base64, ([A-Za-z0-9+\/=]+)"/', $html, $imgMatch);
    expect($imgMatch)->toHaveCount(2);
    $actualQrCode = $imgMatch[1];

    expect($actualQrCode)->toBe(qrCodePngBase64($lnurl, 'img/domains/hu-HU.jpg'))
        ->and($actualQrCode)->not->toBe(qrCodePngBase64($lnurl, 'img/domains/twenty-one.png'));
});

it('embeds the twenty-one motif in the mobile login QR code for a language without its own image (regression)', function () {
    session(['lang_country' => 'fr-FR']);

    $html = $this->get('/auth/mobile?redirect_uri=einundzwanzig%3A%2F%2Fauth')
        ->assertOk()
        ->getContent();

    $lnurl = extractLnurlFromHtml($html);

    preg_match('/data:image\/png;base64, ([A-Za-z0-9+\/=]+)"/', $html, $imgMatch);
    expect($imgMatch)->toHaveCount(2, 'Konnte den eingebetteten QR-Code nicht im HTML finden.');
    $actualQrCode = $imgMatch[1];

    expect($actualQrCode)->toBe(qrCodePngBase64($lnurl, 'img/domains/twenty-one.png'))
        ->and($actualQrCode)->not->toBe(qrCodePngBase64($lnurl, 'img/domains/de-DE.jpg'));
});

/*
 * Every test above compares LoginQrCode with ITSELF, so changing its size,
 * error correction or merge would leave them all green. This one compares it
 * with an image LoginQrCode did not make: the output of
 * simplesoftwareio/simple-qrcode 4.2.0
 * (`format('png')->size(300)->merge($image, .3)->errorCorrection('H')`),
 * the package it replaced, for the content below with
 * public/img/domains/twenty-one.png in the centre. The class promises to
 * reproduce that byte for byte; this is where the promise is checked.
 *
 * Mutation measured 2026-10-25: SIZE 300 -> 200 turns this test red.
 */
it('renders the login QR code byte for byte like simple-qrcode 4.2.0 did', function () {
    $lnurl = 'LNURL1DP68GURN8GHJ7MRWW4EXCTNXD9SHG6NPVCHXXMMD9AKXUATJDSKHQCTE8AEK2UMND9HKU0'.'0';

    $png = LoginQrCode::png($lnurl, public_path('img/domains/twenty-one.png'));

    expect(sha1($png))->toBe(sha1_file(base_path('tests/Fixtures/login-qr-simple-qrcode-4.2.0.png')));
});
