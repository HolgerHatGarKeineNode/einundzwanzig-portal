<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Region;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Germany']);
    $this->region = Region::factory()->create([
        'country_id' => $this->country->id, 'code' => 'by', 'name' => 'Bayern', 'slug' => 'bayern',
    ]);
    $this->city = City::factory()->create([
        'country_id' => $this->country->id, 'region_id' => $this->region->id, 'name' => 'Regensburg Test',
    ]);
    $this->meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Regensburg Meetup']);
});

it('shows the chosen map place on the event page, not just the typed text', function () {
    // Der Anlass: wer im Formular sorgfaeltig einen Ort waehlt, sah davon bisher nichts.
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'location' => 'Im Hinterhof',
        'osm_type' => 'way',
        'osm_id' => 12345,
        'osm_name' => 'Bürgerhaus Regensburg',
        'osm_address' => 'Bürgerhaus, Hauptstraße 1, 93047 Regensburg',
        'start' => now()->addWeek(),
    ]);

    $this->get("/de/meetup/{$this->meetup->slug}/event/{$event->id}")
        ->assertSuccessful()
        ->assertSee('Bürgerhaus Regensburg')
        ->assertSee('Hauptstraße 1', false)
        // Der Freitext bleibt daneben stehen, er sagt etwas anderes.
        ->assertSee('Im Hinterhof')
        ->assertSee('https://www.openstreetmap.org/way/12345', false);
});

it('leaves an event without a map place exactly as it was', function () {
    // 3566 von 3569 Terminen haben keine Referenz — das ist der Normalfall, nicht der
    // Ausnahmefall, und er darf nicht wie ein Fehler aussehen.
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'location' => 'Nur Freitext',
        'osm_type' => null, 'osm_id' => null, 'osm_name' => null,
        'start' => now()->addWeek(),
    ]);

    $this->get("/de/meetup/{$this->meetup->slug}/event/{$event->id}")
        ->assertSuccessful()
        ->assertSee('Nur Freitext')
        ->assertDontSee('openstreetmap.org', false);
});

it('never turns an unknown osm type into a link', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'osm_type' => 'javascript', 'osm_id' => 1, 'osm_name' => 'Kaputt',
        'start' => now()->addWeek(),
    ]);

    $this->get("/de/meetup/{$this->meetup->slug}/event/{$event->id}")
        ->assertSuccessful()
        ->assertSee('Kaputt')
        ->assertDontSee('openstreetmap.org/javascript', false);
});

it('shows the region next to the country in both lists', function () {
    $this->get('/de/cities')->assertSuccessful()->assertSee('Bayern');
    $this->get('/de/meetups')->assertSuccessful()->assertSee('Bayern');
});

it('embeds the region chooser on the pages that have a region route', function () {
    $this->get('/de/meetups')->assertSuccessful()->assertSeeLivewire('region.chooser');
    $this->get('/de/cities')->assertSuccessful()->assertSeeLivewire('region.chooser');
});

it('offers regions only where the country has them', function () {
    /*
     * Auf die Komponente selbst geprueft statt auf Text im Seiten-HTML: Flux rendert
     * die Optionen einer listbox nicht als Klartext, und ob es das tut, ist Flux'
     * Sache und kein Vertrag, den dieser Test halten sollte. Was hier zaehlt, ist
     * die Entscheidung der Komponente.
     */
    Livewire::test('region.chooser', ['country' => 'de', 'pageRoute' => 'meetups.index'])
        ->assertViewHas('supported', true)
        ->assertViewHas('regions', fn ($regions) => $regions->pluck('name')->contains('Bayern'));

    // Land ohne gepflegte Regionen: der Waehler bleibt leer und zeigt sich nicht.
    Country::factory()->create(['code' => 'at', 'name' => 'Austria']);

    Livewire::test('region.chooser', ['country' => 'at', 'pageRoute' => 'meetups.index'])
        ->assertViewHas('regions', fn ($regions) => $regions->isEmpty());
});

it('does not offer itself on a page without a region route', function () {
    // Auf einer Seite ohne Regions-Gegenstueck waere die Auswahl eine Sackgasse.
    Livewire::test('region.chooser', ['country' => 'de', 'pageRoute' => 'meetups.landingpage'])
        ->assertViewHas('supported', false);
});

it('keeps the city list working for cities without a region', function () {
    City::factory()->create([
        'country_id' => $this->country->id, 'region_id' => null, 'name' => 'Ohne Region Test',
    ]);

    $this->get('/de/cities')
        ->assertSuccessful()
        ->assertSee('Ohne Region Test')
        // Kein Bindestrich, kein "keine Region" — die Zeile ist schlicht kuerzer.
        ->assertDontSee('keine Region');
});

it('draws the venue map only when the event carries coordinates', function () {
    $withCoords = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'osm_type' => 'way', 'osm_id' => 12345, 'osm_name' => 'Bürgerhaus Regensburg',
        'osm_lat' => 49.0134, 'osm_lon' => 12.1016,
        'start' => now()->addWeek(),
    ]);

    $this->get("/de/meetup/{$this->meetup->slug}/event/{$withCoords->id}")
        ->assertSuccessful()
        ->assertSee('initVenueMap', false)
        ->assertSee('Anfahrt');

    // Ohne Koordinaten faellt die Karte samt Ueberschrift weg — die Seite sieht aus
    // wie vor dieser Aenderung.
    $withoutCoords = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'location' => 'Nur Freitext',
        'osm_lat' => null, 'osm_lon' => null,
        'start' => now()->addWeek(),
    ]);

    $this->get("/de/meetup/{$this->meetup->slug}/event/{$withoutCoords->id}")
        ->assertSuccessful()
        ->assertDontSee('initVenueMap', false)
        ->assertDontSee('Anfahrt');
});

it('shows the chosen map place in the map popup', function () {
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'location' => 'Im Hinterhof',
        'osm_type' => 'way', 'osm_id' => 12345, 'osm_name' => 'Bürgerhaus Regensburg',
        'start' => now()->addWeek(),
    ]);

    $html = view('components.meetup-popup', [
        'meetup' => $this->meetup->fresh(),
        'url' => '/de/meetup/'.$this->meetup->slug,
        'eventUrl' => null,
    ])->render();

    expect($html)
        ->toContain('Bürgerhaus Regensburg')
        ->toContain('https://www.openstreetmap.org/way/12345')
        // Der Freitext sagt etwas anderes und bleibt daneben stehen.
        ->toContain('Im Hinterhof')
        /*
         * text-zinc-200 stand vor dem festen dunklen Popup-Grund; seit der nur noch im
         * dunklen Theme gilt, waere es auf Leaflets weisser Standardflaeche unlesbar.
         */
        ->not->toContain('text-zinc-200');
});

it('offers the same country picker when editing a city as when creating one', function () {
    $this->actingAs(User::factory()->create());

    // Die Flagge ist der sichtbare Beleg dafuer, dass beide Formulare dieselbe
    // Flux-Listbox verwenden — rohe <option>-Tags koennen kein Bild tragen.
    $flag = 'vendor/blade-flags/country-de.svg';

    $this->get(route('cities.create', ['country' => 'de']))->assertSuccessful()->assertSee($flag, false);
    $this->get(route('cities.edit', ['country' => 'de', 'city' => $this->city]))
        ->assertSuccessful()
        ->assertSee($flag, false);
});
