<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;

/*
 * Zweite Haelfte von N5 (siehe CityFormRedirectStaysRegionFreeTest.php fuer die
 * ausfuehrliche Begruendung): die vier Aufrufe in cities/create und cities/edit sind
 * Redirect UND "Cancel"-Link je Formular. Der Redirect ist per Livewire::test() geprueft,
 * der Cancel-Link (route_with_country('cities.index') in :href) ist ein reiner
 * Blade-Ausdruck und laesst sich am einfachsten am gerenderten Markup der vollen Seite
 * pruefen — beide Routen sind normal per GET erreichbar (routes/web.php:172-173, hinter
 * 'auth').
 *
 * Anker: der <a>, dessen Text "Cancel" ist — NICHT ein globales assertDontSee auf
 * "/us/in/cities". Die Sidebar steht auf JEDER Seite mit und traegt selbst schon den
 * Domain-Rueckfall aus P5 (SidebarRegionRoutePrecedenceTest.php N2): ihr eigener
 * Staedte-Link zeigt auf dieser Domain legitim auf /us/in/cities. Ein globales
 * "kommt nirgends vor" waere also am eigenen Testaufbau gescheitert, nicht am
 * Produktivcode — der Cancel-Link muss gezielt herausgeschnitten werden.
 */
function cancelHref(string $html): ?string
{
    return preg_match('/<a[^>]*href="([^"]+)"[^>]*>\s*Cancel\s*<\/a>/s', $html, $m) ? $m[1] : null;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    test()->actingAs($this->user);

    $this->usa = Country::factory()->create(['code' => 'us']);
    $this->indiana = Region::factory()->indiana()->create(['country_id' => $this->usa->id]);
});

it('keeps the Cancel link on cities/create region-free, even on a region-biased domain (N5, create)', function () {
    $html = test()->get('http://portal.bitcoindiana.org/us/city-create')->assertOk()->getContent();

    expect(cancelHref($html))->toBe('http://portal.bitcoindiana.org/us/cities');
});

it('keeps the Cancel link on cities/edit region-free, even on a region-biased domain (N5, edit)', function () {
    $city = City::factory()->create([
        'country_id' => $this->usa->id,
        'region_id' => $this->indiana->id,
        'created_by' => $this->user->id,
    ]);

    $html = test()->get('http://portal.bitcoindiana.org/us/city-edit/'.$city->id)->assertOk()->getContent();

    expect(cancelHref($html))->toBe('http://portal.bitcoindiana.org/us/cities');
});
