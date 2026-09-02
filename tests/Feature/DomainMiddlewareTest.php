<?php

use App\Http\Middleware\DomainMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/*
 * DomainMiddleware verbiegt config('app.*') und die Session als Seiteneffekt eines
 * Requests. Ein volles `$this->get('/welcome')` mit Host-Header eignet sich hier NICHT
 * als Testweg: die Config, die die Middleware waehrend des Requests setzt, ist nach
 * Rueckkehr von get() bereits wieder auf ihren Ursprungswert zurueckgesetzt (gemessen:
 * config('app.domain_country') las danach 'de', unabhaengig vom Host). Stattdessen wird
 * die Middleware hier direkt aufgerufen — dieselbe Session-Instanz aus dem Container,
 * damit die Middleware ihre lang_country/locale-Weichen wie im echten Request stellen
 * kann.
 */
function invokeDomainMiddleware(string $host, ?string $acceptLanguage = null): void
{
    $request = Request::create("http://{$host}/welcome", 'GET');

    // Request::create() injects its own 'en-us,en;q=0.5' default unless overridden —
    // remove it explicitly to represent a request that truly sent no header at all.
    if ($acceptLanguage !== null) {
        $request->server->set('HTTP_ACCEPT_LANGUAGE', $acceptLanguage);
    } else {
        $request->server->remove('HTTP_ACCEPT_LANGUAGE');
    }

    $request->setLaravelSession(app('session.store'));

    (new DomainMiddleware)->handle($request, fn (Request $req) => new Response('ok'));
}

it('wires Bitcoin Indiana to the US/Indiana region in English (D1)', function () {
    invokeDomainMiddleware('portal.bitcoindiana.org');

    expect(config('app.domain_country'))->toBe('us')
        ->and(config('app.domain_region'))->toBe('in')
        ->and(config('app.name'))->toBe('Bitcoin Indiana')
        ->and(session('lang_country'))->toBe('en-US')
        ->and(App::getLocale())->toBe('en');
});

it('leaves the four existing production domains and the fallback host untouched (D2)', function (string $host, string $expectedCountry) {
    session()->flush();

    invokeDomainMiddleware($host);

    expect(config('app.domain_country'))->toBe($expectedCountry)
        ->and(config('app.domain_region'))->toBeNull();
})->with([
    'einundzwanzig (DE)' => ['portal.einundzwanzig.space', 'de'],
    'eenentwintig (NL)' => ['portal.eenentwintig.net', 'nl'],
    'huszonegy (HU)' => ['portal.huszonegy.world', 'hu'],
    'dwadziesciajeden (PL)' => ['portal.dwadziesciajeden.pl', 'pl'],
    'unknown host falls back to einundzwanzig (DE)' => ['unbekannter-preview-host.example.com', 'de'],
]);

it('resolves a supported browser language ahead of the German domain default on a fresh session', function (string $acceptLanguage, string $expectedLangCountry, string $expectedLocale) {
    session()->flush();

    invokeDomainMiddleware('portal.einundzwanzig.space', $acceptLanguage);

    expect(session('lang_country'))->toBe($expectedLangCountry)
        ->and(session('locale'))->toBe($expectedLocale)
        ->and(App::getLocale())->toBe($expectedLocale);
})->with([
    'Czech' => ['cs-CZ,cs;q=0.9,en;q=0.5', 'cs-CZ', 'cs'],
    'English' => ['en-US,en;q=0.9', 'en-US', 'en'],
]);

it('ignores Accept-Language once the session already carries an explicit lang_country (no regression of issue #18)', function () {
    session()->flush();
    session(['lang_country' => 'de-DE', 'locale' => 'de']);

    invokeDomainMiddleware('portal.eenentwintig.net', 'cs-CZ,cs;q=0.9');

    expect(session('lang_country'))->toBe('de-DE')
        ->and(session('locale'))->toBe('de');
});

it("keeps today's domain default when Accept-Language is missing or names nothing supported", function (?string $acceptLanguage) {
    session()->flush();

    invokeDomainMiddleware('portal.eenentwintig.net', $acceptLanguage);

    expect(session('lang_country'))->toBe('nl-NL')
        ->and(session('locale'))->toBe('nl');
})->with([
    'missing header' => [null],
    'unsupported language only' => ['zh-CN,zh;q=0.9'],
]);
