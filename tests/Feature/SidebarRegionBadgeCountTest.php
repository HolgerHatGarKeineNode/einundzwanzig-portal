<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\Region;

/*
 * P4 (docs/plans/2026-08-24T1738-region-persistenz-navigation.md,
 * sidebar.blade.php:35,114): die Badge-Zahl neben einem regionsfaehigen Link zeigt die
 * ZEILENZAHL DER ZIELLISTE — schraenkt sich also auf dieselbe Region ein, die
 * country_or_region_route() in den href schreibt (->when($navRegionId, ...)). Die
 * Entscheidung "Regionszahl statt Landeszahl" steht im Plan unter "Offene Fragen".
 *
 * Anker: `data-flux-navlist-badge` (vendor/livewire/flux/stubs/.../navlist/badge.blade.php:56)
 * — der Badge-Text selbst ist keine Zahl, die per Regex sicher zu einem bestimmten Link
 * gehoert, weil dieselbe Ziffer mehrfach im Dokument vorkommt (Breadcrumbs, andere
 * Badges). badgeFor() schneidet darum zuerst das <a href="..."> BIS ZUM naechsten
 * schliessenden </a> heraus und sucht das Badge-Attribut NUR darin — eine reine
 * Text-Regex nach der Badge-Zahl allein fand im gerenderten Markup nichts Verlaessliches
 * (siehe Auftrag), das hier ist der Ersatz. Empirisch mit pest --agent kalibriert: drei
 * Faelle (2/1/4) reproduzieren exakt die im Auftrag genannten Zahlen.
 *
 * /us/cities wird bewusst NICHT auf portal.bitcoindiana.org besucht: dort greift bereits
 * der Domain-Rueckfall aus P5 (SidebarRegionRoutePrecedenceTest.php N2), und der href
 * zeigt dann selbst schon auf /us/in/cities — die "volle Landeszahl ohne aktive Region"
 * laesst sich nur auf einer Domain ohne Regionsbindung beobachten.
 */
function badgeFor(string $html, string $href): ?string
{
    if (! preg_match('/<a\s+href="'.preg_quote($href, '/').'".*?<\/a>/s', $html, $anchor)) {
        return null;
    }

    if (! preg_match('/data-flux-navlist-badge[^>]*>([^<]*)</', $anchor[0], $badge)) {
        return null;
    }

    return $badge[1];
}

beforeEach(function () {
    $this->usa = Country::factory()->create(['code' => 'us']);
    $this->de = Country::factory()->create(['code' => 'de']);
    $this->indiana = Region::factory()->indiana()->create(['country_id' => $this->usa->id]);
    $this->nc = Region::factory()->create(['country_id' => $this->usa->id, 'code' => 'nc', 'name' => 'North Carolina']);

    $this->cityIn1 = City::factory()->create(['country_id' => $this->usa->id, 'region_id' => $this->indiana->id]);
    $this->cityIn2 = City::factory()->create(['country_id' => $this->usa->id, 'region_id' => $this->indiana->id]);
    $this->cityNc = City::factory()->create(['country_id' => $this->usa->id, 'region_id' => $this->nc->id]);
    $this->cityNoRegion = City::factory()->create(['country_id' => $this->usa->id, 'region_id' => null]);
    // Eine Stadt in einem anderen Land — zaehlt in KEINER US-Badge mit, egal welcher Fall.
    City::factory()->create(['country_id' => $this->de->id, 'region_id' => null]);
});

it('counts only the region on the cities badge (N4, cities.index-region)', function () {
    $html = test()->get('http://portal.bitcoindiana.org/us/in/cities')->assertOk()->getContent();
    expect(badgeFor($html, 'http://portal.bitcoindiana.org/us/in/cities'))->toBe('2');

    $html = test()->get('http://portal.bitcoindiana.org/us/nc/cities')->assertOk()->getContent();
    expect(badgeFor($html, 'http://portal.bitcoindiana.org/us/nc/cities'))->toBe('1');

    $html = test()->get('http://portal.einundzwanzig.space/us/cities')->assertOk()->getContent();
    expect(badgeFor($html, 'http://portal.einundzwanzig.space/us/cities'))->toBe('4');
});

it('counts only the region on the meetups badge (N4, meetups.index-region)', function () {
    Meetup::factory()->create(['city_id' => $this->cityIn1->id]);
    Meetup::factory()->create(['city_id' => $this->cityIn2->id]);
    Meetup::factory()->create(['city_id' => $this->cityNc->id]);
    Meetup::factory()->create(['city_id' => $this->cityNoRegion->id]);

    $html = test()->get('http://portal.bitcoindiana.org/us/in/meetups')->assertOk()->getContent();
    expect(badgeFor($html, 'http://portal.bitcoindiana.org/us/in/meetups'))->toBe('2');

    $html = test()->get('http://portal.bitcoindiana.org/us/nc/meetups')->assertOk()->getContent();
    expect(badgeFor($html, 'http://portal.bitcoindiana.org/us/nc/meetups'))->toBe('1');

    $html = test()->get('http://portal.einundzwanzig.space/us/meetups')->assertOk()->getContent();
    expect(badgeFor($html, 'http://portal.einundzwanzig.space/us/meetups'))->toBe('4');
});
