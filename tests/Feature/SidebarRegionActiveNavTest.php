<?php

use App\Models\Country;
use App\Models\Region;

/*
 * Deckt den Fix in resources/views/components/layouts/app/sidebar.blade.php (commit
 * 3b5935d, P1 aus docs/plans/2026-08-24T1738-region-persistenz-navigation.md): die drei
 * regionsfaehigen Navlist-Eintraege (Meetups, Karte, Staedte) nennen jetzt ihre
 * Regionsvariante in routeIs(), damit auf /us/in/... etwas markiert bleibt.
 *
 * Flux rendert den aktiven Zustand als eigenes Attribut auf dem <a>-Tag,
 * `data-current="data-current"` — abwesend, wenn :current false ist (kein
 * data-current="false"). Das ist der einzige belastbare Anker: eine reine Textsuche nach
 * "Meetups" traefe auch Badge-Zahlen und den Breadcrumb der Seite selbst. Gemessen per
 * pest --agent vor dem Schreiben dieses Tests (siehe Bericht).
 */

function activeNavHrefs(string $html): array
{
    preg_match_all('/<a\s+href="([^"]+)"[^>]*>/', $html, $matches, PREG_SET_ORDER);

    return array_values(array_map(
        fn (array $m) => $m[1],
        array_filter($matches, fn (array $m) => str_contains($m[0], 'data-current="data-current"'))
    ));
}

beforeEach(function () {
    $usa = Country::factory()->create(['code' => 'us']);
    Region::factory()->indiana()->create(['country_id' => $usa->id]);
    Country::factory()->create(['code' => 'de']);
});

it('marks exactly one navlist item active on each of the three region pages (N1)', function (string $path) {
    $html = test()->get('http://portal.bitcoindiana.org'.$path)->assertOk()->getContent();

    expect(activeNavHrefs($html))->toHaveCount(1);
})->with([
    'meetups' => ['/us/in/meetups'],
    'map' => ['/us/in/map'],
    'cities' => ['/us/in/cities'],
]);

it('leaves plain country routes and a region-less domain at exactly one active item (N2)', function (string $host, string $path) {
    $html = test()->get("http://{$host}{$path}")->assertOk()->getContent();

    expect(activeNavHrefs($html))->toHaveCount(1);
})->with([
    'bitcoindiana country route: meetups' => ['portal.bitcoindiana.org', '/us/meetups'],
    'bitcoindiana country route: cities' => ['portal.bitcoindiana.org', '/us/cities'],
    'einundzwanzig, no region at all: meetups' => ['portal.einundzwanzig.space', '/de/meetups'],
]);

it('activates the matching navlist item, not merely any item (N3)', function (string $path, string $expectedHref) {
    $html = test()->get('http://portal.bitcoindiana.org'.$path)->assertOk()->getContent();

    expect(activeNavHrefs($html))->toBe(['http://portal.bitcoindiana.org'.$expectedHref]);
})->with([
    'meetups region page activates the Meetups item' => ['/us/in/meetups', '/us/meetups'],
    'map region page activates the Karte item' => ['/us/in/map', '/us/map'],
    'cities region page activates the Staedte item' => ['/us/in/cities', '/us/cities'],
]);

it('does not also activate meetups.index-all or meetups.map-world on a region page (N4)', function () {
    $meetupsHtml = test()->get('http://portal.bitcoindiana.org/us/in/meetups')->assertOk()->getContent();
    $mapHtml = test()->get('http://portal.bitcoindiana.org/us/in/map')->assertOk()->getContent();

    expect(activeNavHrefs($meetupsHtml))->not->toContain('http://portal.bitcoindiana.org/us/all-meetups')
        ->and(activeNavHrefs($mapHtml))->not->toContain('http://portal.bitcoindiana.org/us/map-world');
});
