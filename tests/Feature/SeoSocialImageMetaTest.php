<?php

use App\Attributes\SeoDataAttribute;

/*
 * og:image:width/height entstehen nur, weil SeoDataAttribute::initDefinitions()
 * `imageMeta` jetzt EXPLIZIT mitgibt. SEOData wuerde es sonst lazy aus `image`
 * bauen (einer absoluten URL), und ImageMeta gibt bei einer URL sofort auf
 * (`filter_var(..., FILTER_VALIDATE_URL)` -> return) — width/height blieben
 * dann still null, ohne dass irgendein Aufruf fehlschlaegt. Genau das ist der
 * Punkt, der beim naechsten Refactoring am leisesten kaputtgeht.
 */

it('gives the welcome page absolute width/height metadata for its social image', function (string $langCountry, int $expectedWidth, int $expectedHeight) {
    session(['lang_country' => $langCountry]);

    $seoData = SeoDataAttribute::getData('welcome');

    expect($seoData->imageMeta)->not->toBeNull()
        ->and($seoData->imageMeta->width)->toBe($expectedWidth)
        ->and($seoData->imageMeta->height)->toBe($expectedHeight);
})->with([
    'englisch (1,91:1 vorschau-format)' => ['en-US', 1200, 630],
    'deutsch (quadratisches domain-motiv)' => ['de-DE', 512, 512],
    'ungarisch' => ['hu-HU', 320, 320],
    'lateinamerikanisches spanisch' => ['es-CL', 360, 360],
]);

it('keeps image absolute while imageMeta stays a bare public-relative path (regression guard)', function () {
    // Der Kern des Fixes: ImageMeta gibt bei einer URL in $path sofort auf.
    // Wuerde `imageMeta: new ImageMeta($domainImage)` (die absolute URL statt
    // des Pfads) wieder eingefuehrt, bliebe width/height fuer immer null,
    // ohne dass ein Aufruf sichtbar scheitert — genau dieser Fall wird hier
    // durch eine Mutationsprobe belegt (siehe Bericht).
    session(['lang_country' => 'en-US']);

    $seoData = SeoDataAttribute::getData('welcome');

    expect($seoData->image)->toStartWith('http')
        ->and($seoData->imageMeta->width)->not->toBeNull()
        ->and($seoData->imageMeta->height)->not->toBeNull();
});
