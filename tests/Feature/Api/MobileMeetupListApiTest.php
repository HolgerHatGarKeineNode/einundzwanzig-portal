<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create([
        'country_id' => $country->id,
        'name' => 'Berlin',
        'latitude' => 52.52,
        'longitude' => 13.405,
    ]);
});

it('returns only visible meetups in the lean mobile shape', function () {
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'visible_on_map' => true,
        'name' => 'Sichtbar',
        'slug' => 'sichtbar',
    ]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDays(3)->setTime(19, 0),
    ]);
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'visible_on_map' => false,
        'name' => 'Versteckt',
    ]);

    $response = $this->getJson('/api/mobile/meetups');

    $response->assertSuccessful()
        ->assertJsonStructure([['name', 'slug', 'city', 'country', 'latitude', 'longitude', 'logo', 'next_event_start']]);

    $payload = collect($response->json());
    expect($payload->pluck('name')->all())->toContain('Sichtbar')->not->toContain('Versteckt');

    $entry = $payload->firstWhere('name', 'Sichtbar');
    expect($entry)
        ->slug->toBe('sichtbar')
        ->city->toBe('Berlin')
        ->country->toBe('DE')
        ->latitude->toBe(52.52)
        ->longitude->toBe(13.405)
        // Ohne echtes Logo NULL (nicht die Fallback-Platzhalter-URL der
        // Media-Collection) — die App zeigt sonst statt Initialen ein Bild.
        ->logo->toBeNull()
        ->next_event_start->toBe(now()->addDays(3)->setTime(19, 0)->format('Y-m-d H:i'));

    // Kein Intro/keine Socials/kein RSVP-Zähler im schlanken Format.
    expect(array_keys($entry))->not->toContain('intro', 'rsvp_enabled', 'attendees');
});

it('orders meetups by nearest upcoming event, event-less last, then by name', function () {
    $late = Meetup::factory()->create(['city_id' => $this->city->id, 'visible_on_map' => true, 'name' => 'Later']);
    MeetupEvent::factory()->create(['meetup_id' => $late->id, 'start' => now()->addMonth()]);

    $early = Meetup::factory()->create(['city_id' => $this->city->id, 'visible_on_map' => true, 'name' => 'Earlier']);
    MeetupEvent::factory()->create(['meetup_id' => $early->id, 'start' => now()->addDay()]);

    // Zwei ohne Termin → müssen ans Ende, untereinander nach Name (Anna vor Zoe).
    Meetup::factory()->create(['city_id' => $this->city->id, 'visible_on_map' => true, 'name' => 'Zoe (no event)']);
    Meetup::factory()->create(['city_id' => $this->city->id, 'visible_on_map' => true, 'name' => 'Anna (no event)']);

    $order = collect($this->getJson('/api/mobile/meetups')->json())->pluck('name');

    expect($order->all())->toBe(['Earlier', 'Later', 'Anna (no event)', 'Zoe (no event)']);
});

it('reports next_event_start as null when there is no upcoming event', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'visible_on_map' => true]);
    // Nur ein vergangener Termin → kein kommender.
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->subWeek()]);

    $entry = collect($this->getJson('/api/mobile/meetups')->json())->firstWhere('name', $meetup->name);

    expect($entry['next_event_start'])->toBeNull();
});

it('picks the nearest upcoming event, not a later or past one', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'visible_on_map' => true, 'name' => 'Naechster']);
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->subDay()]);          // vergangen
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addMonth()]);          // spät
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addDays(2)->setTime(18, 30)]); // nächster

    $entry = collect($this->getJson('/api/mobile/meetups')->json())->firstWhere('name', 'Naechster');

    expect($entry['next_event_start'])->toBe(now()->addDays(2)->setTime(18, 30)->format('Y-m-d H:i'));
});

it('runs a constant number of queries regardless of meetup count (no N+1)', function () {
    // 20 Meetups mit je 3 Terminen — die Query-Zahl darf NICHT mit der
    // Meetup-Zahl skalieren (sonst wäre der nextEvent-N+1 zurück).
    Meetup::factory()->count(20)->create(['city_id' => $this->city->id, 'visible_on_map' => true])
        ->each(fn (Meetup $m) => MeetupEvent::factory()->count(3)->create([
            'meetup_id' => $m->id,
            'start' => now()->addDays(fake()->numberBetween(1, 40)),
        ]));

    DB::enableQueryLog();
    $this->getJson('/api/mobile/meetups')->assertSuccessful();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Basis: Meetups (inkl. Subquery) + City + Country + Media = wenige,
    // konstante Abfragen. Großzügige Obergrenze, aber weit unter 20+.
    expect($count)->toBeLessThanOrEqual(6);
});
