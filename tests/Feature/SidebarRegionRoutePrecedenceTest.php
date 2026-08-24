<?php

use App\Models\Country;
use App\Models\Region;

/*
 * P4+P5 (docs/plans/2026-08-24T1738-region-persistenz-navigation.md): sidebar.blade.php
 * ruft fuer die drei regionsfaehigen Ziele country_or_region_route() statt
 * route_with_country() auf (P4), und der Helper liest die Region seitdem zuerst aus der
 * LAUFENDEN ROUTE, die Domain nur als Rueckfall (P5, app/helpers.php: active_region_for()).
 *
 * Zwei Zusagen, die sich nur an ECHTEN Bundesstaaten ausserhalb Indianas trennen lassen —
 * North Carolina steht hier, weil ein Regionswechsel weg von der Domain-Region der
 * einzige Fall ist, an dem "Route gewinnt" und "Domain gewinnt" unterschiedliche Antworten
 * geben. Mit Indiana allein waeren beide Zusagen von aussen nicht zu unterscheiden.
 *
 * Anker: der EXAKTE href-String im gerenderten <a>, nicht nur die Anzahl aktiver Punkte
 * (das deckt SidebarRegionActiveNavTest.php bereits ab). assertSee(..., false) sucht
 * ungeankert im ganzen Dokument — fuer diese Zusage reicht das, weil Region-Segmente aus
 * den drei betroffenen Routen (meetups/map/cities) nicht als Substring einer anderen
 * vorkommenden URL auftreten (geprueft per pest --agent vor dem Schreiben).
 */
beforeEach(function () {
    $usa = Country::factory()->create(['code' => 'us']);
    Region::factory()->indiana()->create(['country_id' => $usa->id]);
    $this->nc = Region::factory()->create(['country_id' => $usa->id, 'code' => 'nc', 'name' => 'North Carolina']);
});

it('lets the route\'s region win over the domain region on all three sidebar links (N1)', function () {
    $response = test()->get('http://portal.bitcoindiana.org/us/nc/cities')->assertOk();

    $response->assertSee('href="http://portal.bitcoindiana.org/us/nc/meetups"', false)
        ->assertSee('href="http://portal.bitcoindiana.org/us/nc/map"', false)
        ->assertSee('href="http://portal.bitcoindiana.org/us/nc/cities"', false)
        ->assertDontSee('href="http://portal.bitcoindiana.org/us/in/meetups"', false)
        ->assertDontSee('href="http://portal.bitcoindiana.org/us/in/map"', false)
        ->assertDontSee('href="http://portal.bitcoindiana.org/us/in/cities"', false);
});

it('falls back to the domain region when the route itself names none (N2)', function () {
    $response = test()->get('http://portal.bitcoindiana.org/us/cities')->assertOk();

    $response->assertSee('href="http://portal.bitcoindiana.org/us/in/meetups"', false)
        ->assertSee('href="http://portal.bitcoindiana.org/us/in/map"', false)
        ->assertSee('href="http://portal.bitcoindiana.org/us/in/cities"', false);
});

it('leaves a region-less domain fully unchanged on the plain country route (N2, Gegenprobe)', function () {
    Country::factory()->create(['code' => 'de']);

    $response = test()->get('http://portal.einundzwanzig.space/de/cities')->assertOk();

    $response->assertSee('href="http://portal.einundzwanzig.space/de/meetups"', false)
        ->assertSee('href="http://portal.einundzwanzig.space/de/map"', false)
        ->assertSee('href="http://portal.einundzwanzig.space/de/cities"', false);
});
