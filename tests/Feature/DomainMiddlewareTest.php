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
function invokeDomainMiddleware(string $host): void
{
    $request = Request::create("http://{$host}/welcome", 'GET');
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
