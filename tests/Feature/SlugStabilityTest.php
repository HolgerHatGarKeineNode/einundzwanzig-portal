<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\SelfHostedService;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);
});

it('keeps the meetup slug when the meetup is renamed', function () {
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Einundzwanzig Köln',
    ]);
    $slug = $meetup->slug;

    $meetup->update(['name' => 'Bitcoin Meetup Köln']);

    expect($meetup->fresh()->slug)->toBe($slug);
});

it('keeps the original meetup url alive after a rename', function () {
    // Der eigentliche Schaden: der Slug ist Route-Key. Aenderte er sich, waere jeder
    // geteilte Link, QR-Code und Suchmaschinentreffer ein 404.
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Einundzwanzig Lübeck',
    ]);
    $url = "/de/meetup/{$meetup->slug}";

    $this->get($url)->assertSuccessful();

    $meetup->update(['name' => 'Etwas ganz anderes']);

    $this->get($url)->assertSuccessful();
});

it('does not let the saving users language move the slug', function () {
    // Gemessen am 2026-08-22: 37 der 314 Produktions-Meetups haetten sich allein dadurch
    // verschoben, dass jemand mit englischer Oberflaeche speichert — Str::slug macht aus
    // "Köln" nur im deutschen Modus "koeln".
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Einundzwanzig Köln',
        // Die Factory setzt sonst einen eigenen Slug, und ein explizit gesetzter Slug
        // gewinnt gegen HasSlug (GenerateSlugAction::hasCustomSlugBeenUsed) — dann
        // pruefte dieser Test die Factory statt das Model.
        'slug' => null,
    ]);

    expect($meetup->slug)->toContain('koeln');

    $slug = $meetup->slug;

    Cookie::queue('lang', 'en');
    $meetup->update(['last_event_at' => now()]);

    expect($meetup->fresh()->slug)->toBe($slug);
});

it('generates the city slug from the HasSlug rule, not from the form', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('cities.create')
        ->set('name', 'Würzburg Test')
        ->set('country_id', $this->country->id)
        ->set('latitude', 49.7913)
        ->set('longitude', 9.9534)
        ->call('createCity')
        ->assertHasNoErrors();

    // 'de-' Praefix aus generateSlugsFrom(['country.code', 'name']), Umlaut deutsch
    // transliteriert — das Formular haette 'wurzburg-test' erzeugt.
    expect(City::firstWhere('name', 'Würzburg Test')?->slug)->toBe('de-wuerzburg-test');
});

it('keeps the city slug when the city is updated', function () {
    $city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Slug Stability City',
    ]);
    $slug = $city->slug;

    $city->update(['population' => 12345]);

    expect($city->fresh()->slug)->toBe($slug);
});

it('keeps the service slug and its url after a rename', function () {
    $service = SelfHostedService::factory()->create(['name' => 'Mein Dienst']);
    $url = "/de/service/{$service->slug}";

    $this->get($url)->assertSuccessful();

    $service->update(['name' => 'Anderer Name']);

    expect($service->fresh()->slug)->toBe($service->slug);
    $this->get($url)->assertSuccessful();
});
