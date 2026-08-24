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

/*
 * P4 (docs/plans/2026-08-24T1738-region-persistenz-navigation.md) stellt die drei
 * regionsfaehigen Links auf country_or_region_route() um: der href zeigt seitdem auf
 * /us/in/... statt wie zuvor auf die Landesroute /us/.... Die urspruengliche Erwartung
 * dieses Tests (expectedHref = Landesroute) ist damit ueberholt, nicht falsch entdeckt —
 * sie hielt exakt den Vertrag fest, der bis P4 galt (siehe P1-Bericht oben:
 * "Der href taugt nicht als Anker ... weil er bis P4 weiter auf die Landesroute zeigt").
 *
 * Mit dem P4-Umbau faellt expectedHref auf denselben Wert wie der besuchte Pfad — die
 * Faelle unten sehen dadurch wie ein Tautologie-Datensatz aus (erwarteter Wert = Eingabe).
 * Das ist real, aber ungefaehrlich: href (country_or_region_route()) und :current
 * (request()->routeIs(...)) sind zwei UNABHAENGIG berechnete Blade-Ausdruecke, keiner
 * leitet sich vom anderen ab. Ein P1-Regressionsfall (routeIs() verliert den
 * -region-Namen) bleibt weiterhin roetbar: dann faellt der Zaehler auf 0 aktive Punkte,
 * toBe() gegen ein Einzelelement schlaegt fehl. Ein P4-Regressionsfall (href faellt
 * zurueck auf die Landesroute, waehrend :current weiter auf die Regionsroute zielt)
 * bleibt ebenfalls roetbar: dann liefert der href /us/meetups statt /us/in/meetups.
 *
 * Was dieser Test NICHT mehr zeigt: dass der aktive href von der besuchten URL abweicht.
 * Diese Eigenschaft hat SidebarRegionRoutePrecedenceTest.php N2 uebernommen — dort bleibt
 * der Unterschied zwischen href und Pfad real (Domain-Rueckfall auf einer Landesroute),
 * und genau das schliesst die hier entstandene Luecke.
 */
it('activates the matching navlist item, not merely any item (N3)', function (string $path, string $expectedHref) {
    $html = test()->get('http://portal.bitcoindiana.org'.$path)->assertOk()->getContent();

    expect(activeNavHrefs($html))->toBe(['http://portal.bitcoindiana.org'.$expectedHref]);
})->with([
    'meetups region page activates the Meetups item' => ['/us/in/meetups', '/us/in/meetups'],
    'map region page activates the Karte item' => ['/us/in/map', '/us/in/map'],
    'cities region page activates the Staedte item' => ['/us/in/cities', '/us/in/cities'],
]);

it('does not also activate meetups.index-all or meetups.map-world on a region page (N4)', function () {
    $meetupsHtml = test()->get('http://portal.bitcoindiana.org/us/in/meetups')->assertOk()->getContent();
    $mapHtml = test()->get('http://portal.bitcoindiana.org/us/in/map')->assertOk()->getContent();

    expect(activeNavHrefs($meetupsHtml))->not->toContain('http://portal.bitcoindiana.org/us/all-meetups')
        ->and(activeNavHrefs($mapHtml))->not->toContain('http://portal.bitcoindiana.org/us/map-world');
});
