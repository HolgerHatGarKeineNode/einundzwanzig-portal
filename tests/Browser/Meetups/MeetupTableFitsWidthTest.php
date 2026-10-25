<?php

use App\Models\City;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Region;

/*
 * The meetup list must never scroll sideways. Flux renders its table with
 * `whitespace-nowrap` and wraps it in an `overflow-auto` scroll area, so one
 * long event title or region name stretched its column and pushed the last
 * column ("Log in") out of view behind a horizontal scrollbar — reported on
 * production at desktop width, 2026-10-25.
 *
 * Geometry, not classes: the scroll area and the document are measured at a
 * phone width and at desktop width with the sidebar open.
 */

beforeEach(function () {
    $country = portalCountry();
    $region = Region::factory()->create(['country_id' => $country->id, 'name' => 'Mecklenburg-Vorpommern-Nordwestküste']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'region_id' => $region->id,
        'name' => 'Neumarkt in der Oberpfalz an der Sulz',
    ]);

    $meetup = Meetup::factory()->active()->create([
        'city_id' => $city->id,
        'name' => 'Einundzwanzig Neumarkt in der Oberpfalz und Umgebung',
        'telegram_link' => 'https://t.me/einundzwanzig',
        'webpage' => 'https://example.org',
        'twitter_username' => 'einundzwanzig',
        'matrix_group' => 'https://matrix.to/#/#einundzwanzig:matrix.org',
        'nostr' => 'npub1pt0kw36ue3w2g4haxq3wgm6a2fhtptmzsjlc2j2vphtcgle72qesgpjyc6',
        'simplex' => 'https://simplex.chat/contact',
        'signal' => 'https://signal.group/einundzwanzig',
    ]);

    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'title' => 'PlebWalk mit Stadtführung: Auf den Spuren von Jakob Fugger durch die ganze Altstadt',
    ]);

    $this->measure = function ($page, int $width, int $height): array {
        $page->resize($width, $height)->wait(1.2);
        $page->script('document.documentElement.getBoundingClientRect().width');
        $page->wait(0.5);

        return $page->script(<<<'JS'
            (() => {
              const area = document.querySelector('ui-table-scroll-area');
              return {
                viewport: window.innerWidth,
                found: !! area,
                areaScroll: area ? area.scrollWidth : 0,
                areaClient: area ? area.clientWidth : 0,
                docScroll: document.documentElement.scrollWidth,
                docClient: document.documentElement.clientWidth,
              };
            })()
        JS);
    };
});

it('fits the meetup table into the viewport without horizontal scrolling', function () {
    $page = visit(route('meetups.index', ['country' => 'de'], absolute: false));

    foreach ([[390, 844], [1280, 900], [1600, 1000]] as [$width, $height]) {
        $m = ($this->measure)($page, $width, $height);

        expect($m['found'])->toBeTrue();
        expect($m['areaScroll'])->toBeLessThanOrEqual($m['areaClient'], sprintf(
            'viewport %s: the table scrolls sideways (%s > %s)', $m['viewport'], $m['areaScroll'], $m['areaClient']
        ));
        expect($m['docScroll'])->toBeLessThanOrEqual($m['docClient'], sprintf(
            'viewport %s: the page scrolls sideways (%s > %s)', $m['viewport'], $m['docScroll'], $m['docClient']
        ));
    }

    $page->assertSee('PlebWalk')->assertNoJavaScriptErrors();
});
