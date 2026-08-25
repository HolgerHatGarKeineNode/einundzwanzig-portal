<?php

use App\Attributes\SeoDataAttribute;
use App\Http\Middleware\DomainMiddleware;
use Illuminate\Http\Request;

/**
 * og:site_name folgt der Domain, nicht der Nutzersprache.
 *
 * Vorher las get_domain_attributes() aus $countrySiteNameMapping, einer
 * Variablen, die es nie gab — der Wert war damit immer der Fallback. Er fiel
 * nur deshalb nicht auf, weil SeoDataAttribute ihn gar nicht verwendete,
 * sondern __('EINUNDZWANZIG Portal') und damit die Sprache statt der Domain.
 */
function passThroughDomain(string $host): void
{
    $request = Request::create("https://{$host}/de/meetups");
    (new DomainMiddleware)->handle($request, fn () => response(''));
}

it('takes the site name from the configured app name', function () {
    config(['app.name' => 'EENENTWINTIG Portaal']);

    expect(get_domain_attributes()['siteName'])->toBe('EENENTWINTIG Portaal');
});

it('derives the site name from the host via DomainMiddleware', function (string $host, string $expected) {
    passThroughDomain($host);

    expect(get_domain_attributes()['siteName'])->toBe($expected);
})->with([
    'deutsch' => ['portal.einundzwanzig.space', 'EINUNDZWANZIG Portal'],
    'niederlaendisch' => ['portal.eenentwintig.net', 'EENENTWINTIG Portaal'],
    'ungarisch' => ['portal.huszonegy.world', 'HUSZONEGY Portál'],
    'polnisch' => ['portal.dwadziesciajeden.pl', 'DWADZIEŚCIA JEDEN Portal'],
]);

it('puts the site name into the SEO data instead of the translated string', function () {
    config(['app.name' => 'HUSZONEGY Portál']);

    expect(SeoDataAttribute::getData('login')->site_name)->toBe('HUSZONEGY Portál');
});

it('does not freeze the site name after the first lookup', function () {
    // Der statische Definitions-Cache lieferte sonst dauerhaft den Namen des
    // ersten Aufrufs — im selben Prozess also die falsche Marke.
    config(['app.name' => 'EINUNDZWANZIG Portal']);
    expect(SeoDataAttribute::getData('login')->site_name)->toBe('EINUNDZWANZIG Portal');

    config(['app.name' => 'DWADZIEŚCIA JEDEN Portal']);
    expect(SeoDataAttribute::getData('login')->site_name)->toBe('DWADZIEŚCIA JEDEN Portal');
});

it('gives spanish-speaking latin america the lat image and the veintiuno brand', function (string $langCountry) {
    // Der Zweig war dreifach tot: falsch geformte Codes (ar-AR statt es-AR),
    // keiner davon in config/lang-country.php, und der Bild-Fallback setzte
    // lang_country vorher auf de-DE zurück, weil lat.png kein .jpg ist.
    session(['lang_country' => $langCountry]);

    expect(get_domain_attributes())
        ->image->toContain('img/domains/lat.png')
        ->author->toBe('veintiuno')
        ->twitter->toBe('veintiunolat');
})->with(['es-CL', 'es-CO']);

it('falls back to the twenty-one image for countries without their own (regression: no longer german)', function () {
    // Bis 2026-08-25 fiel der Bild-Fallback hart auf 'de-DE.jpg' — jede
    // Fassung ohne eigenes Motiv (hier lv-LV) bekam damit das deutsche Bild.
    // TWENTY ONE ist die sprachneutrale Marke und damit der richtige Default.
    session(['lang_country' => 'lv-LV']);

    expect(get_domain_attributes())
        ->image->toContain('img/domains/twenty-one.png')
        ->image->not->toContain('img/domains/de-DE.jpg')
        ->author->toBe('einundzwanzig');
});

it('keeps its own image for countries that have one', function () {
    session(['lang_country' => 'hu-HU']);

    expect(get_domain_attributes())
        ->image->toContain('img/domains/hu-HU.jpg')
        ->author->toBe('huszonegy')
        ->twitter->toBe('HUSZONEGYworld');
});

it('keeps the brand when the user switches the interface language', function () {
    // Ein Deutscher auf der niederlaendischen Domain liest weiterhin auf dem
    // EENENTWINTIG Portaal — die Site heisst so, unabhaengig von der Sprache.
    config(['app.name' => 'EENENTWINTIG Portaal']);
    app()->setLocale('de');

    expect(SeoDataAttribute::getData('login')->site_name)->toBe('EENENTWINTIG Portaal');
});
