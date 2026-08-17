<?php

use App\Enums\RecurrenceType;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Services\Osm\NominatimClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    NominatimClient::resetThrottle();
    Cache::flush();
    app()->bind(NominatimClient::class, fn (): NominatimClient => new NominatimClient(minIntervalMs: 0));

    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $city->id, 'created_by' => actingAsUser()->id]);
});

function osmResponse(string $name = 'Bitcoin Bar'): array
{
    return [[
        'osm_type' => 'node',
        'osm_id' => 4711,
        'name' => $name,
        'display_name' => "{$name}, Hauptstraße 1, Nürnberg",
        'lat' => '49.4521',
        'lon' => '11.0767',
    ]];
}

it('finds and picks a place', function () {
    Http::fake(['*' => Http::response(osmResponse())]);

    Livewire::test('osm.place-picker')
        ->set('query', 'Bitcoin Bar Nürnberg')
        ->call('search')
        ->assertCount('results', 1)
        ->call('choose', 0)
        ->assertSet('place.osm_id', 4711)
        ->assertSet('place.osm_name', 'Bitcoin Bar')
        ->assertSet('results', []);
});

it('keeps only the stored columns, not the ranking aids', function () {
    Http::fake(['*' => Http::response([[
        ...osmResponse()[0],
        'importance' => 0.9,
        'category' => 'amenity',
    ]])]);

    $place = Livewire::test('osm.place-picker')
        ->set('query', 'Bitcoin Bar')
        ->call('search')
        ->call('choose', 0)
        ->get('place');

    expect(array_keys($place))->toBe([
        'osm_type', 'osm_id', 'osm_name', 'osm_address', 'osm_lat', 'osm_lon',
    ]);
});

it('does not call out for a query under three characters', function () {
    Http::fake(['*' => Http::response(osmResponse())]);

    Livewire::test('osm.place-picker')->set('query', 'ab')->call('search')->assertSet('results', []);

    Http::assertNothingSent();
});

it('offers the text field instead when nothing is found', function () {
    Http::fake(['*' => Http::response([])]);

    Livewire::test('osm.place-picker')
        ->set('query', 'Hinterzimmer Müller')
        ->call('search')
        ->assertSet('results', [])
        ->assertSee('Trag den Ort einfach als Text ein');
});

it('survives a dead geocoding service', function () {
    Http::fake(['*' => Http::response('down', 503)]);

    Livewire::test('osm.place-picker')
        ->set('query', 'Bitcoin Bar')
        ->call('search')
        ->assertOk()
        ->assertSet('results', []);
});

it('clears a chosen place', function () {
    Http::fake(['*' => Http::response(osmResponse())]);

    Livewire::test('osm.place-picker')
        ->set('query', 'Bitcoin Bar')
        ->call('search')
        ->call('choose', 0)
        ->call('clearPlace')
        ->assertSet('place', []);
});

it('saves the place onto a new meetup event', function () {
    Http::fake(['*' => Http::response(osmResponse())]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Café Test')
        ->set('description', 'Ein Test-Event')
        ->set('link', 'https://example.com')
        ->set('osmPlace', [
            'osm_type' => 'node', 'osm_id' => 4711, 'osm_name' => 'Bitcoin Bar',
            'osm_address' => 'Hauptstraße 1', 'osm_lat' => 49.4521, 'osm_lon' => 11.0767,
        ])
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->first();

    expect($event->osm_id)->toBe(4711)
        ->and($event->osm_name)->toBe('Bitcoin Bar')
        ->and((float) $event->osm_lat)->toBe(49.4521);
});

it('loads an existing place back into the form', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'osm_type' => 'node', 'osm_id' => 4711, 'osm_name' => 'Bitcoin Bar',
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup, 'event' => $event])
        ->assertSet('osmPlace.osm_id', 4711);
});

it('clears the columns when the place is removed', function () {
    // Omitting the keys instead of nulling them would silently keep the old location.
    // recurrence_type null: the factory sets it at random, which would put the form
    // into series mode and demand a series end date this test does not care about.
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'recurrence_type' => null,
        'osm_type' => 'node', 'osm_id' => 4711, 'osm_name' => 'Bitcoin Bar',
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup, 'event' => $event])
        ->set('osmPlace', [])
        ->set('location', 'Nur noch Text')
        ->set('description', 'Beschreibung')
        ->set('link', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->osm_id)->toBeNull()
        ->and($event->fresh()->osm_name)->toBeNull();
});

it('gives every occurrence of a series the same place', function () {
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Café Test')
        ->set('description', 'Ein Test-Event')
        ->set('link', 'https://example.com')
        ->set('seriesMode', true)
        ->set('endDate', now()->addWeeks(4)->format('Y-m-d'))
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('osmPlace', [
            'osm_type' => 'node', 'osm_id' => 4711, 'osm_name' => 'Bitcoin Bar',
            'osm_address' => 'Hauptstraße 1', 'osm_lat' => 49.4521, 'osm_lon' => 11.0767,
        ])
        ->call('save')
        ->assertHasNoErrors();

    $events = MeetupEvent::query()->where('meetup_id', $this->meetup->id)->get();

    expect($events->count())->toBeGreaterThan(1)
        ->and($events->pluck('osm_id')->unique()->all())->toBe([4711]);
});

it('narrows the search to the meetup country', function () {
    Http::fake(['*' => Http::response(osmResponse())]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->assertSeeLivewire('osm.place-picker');

    Livewire::test('osm.place-picker', ['countryCode' => 'cz'])
        ->set('query', 'Bitcoin Bar')
        ->call('search');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'countrycodes=cz'));
});

it('nudges only on an existing event without a place', function () {
    // A brand new event is being filled in right now — warning about a field the user
    // has not reached yet is noise, not help.
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->assertSet('needsOsmHint', false);

    $withoutPlace = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup, 'event' => $withoutPlace])
        ->assertSet('needsOsmHint', true)
        ->assertSee('noch keinen Kartenort');

    $withPlace = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'osm_type' => 'node', 'osm_id' => 4711, 'osm_name' => 'Bitcoin Bar',
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup, 'event' => $withPlace])
        ->assertSet('needsOsmHint', false)
        ->assertDontSee('noch keinen Kartenort');
});
