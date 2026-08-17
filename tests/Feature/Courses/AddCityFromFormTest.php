<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Services\Osm\NominatimClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The course event form requires a city, and until now a missing one was a dead end: the
 * only way to add it was to leave the form and lose everything typed so far.
 *
 * Coordinates come from the geocoder rather than the user because `cities.latitude` and
 * `longitude` are NOT NULL and nobody knows their town's coordinates by heart.
 */
beforeEach(function () {
    NominatimClient::resetThrottle();
    Cache::flush();
    app()->bind(NominatimClient::class, fn (): NominatimClient => new NominatimClient(minIntervalMs: 0));

    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $user = actingAsUser();
    $this->course = Course::factory()->create();
    $this->course->forceFill(['created_by' => $user->id])->save();
});

function cityResponse(string $name = 'Lippstadt', string $category = 'place'): array
{
    return [[
        'osm_type' => 'relation',
        'osm_id' => 123456,
        'name' => $name,
        'display_name' => "{$name}, Kreis Soest, Nordrhein-Westfalen, Deutschland",
        'lat' => '51.6739',
        'lon' => '8.3444',
        'category' => $category,
    ]];
}

function courseEventForm()
{
    return Livewire::test('courses.create-edit-events', ['course' => test()->course]);
}

it('creates the city from a search result and selects it', function () {
    Http::fake(['*' => Http::response(cityResponse())]);

    courseEventForm()
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertCount('newCityResults', 1)
        ->call('useCity', 0)
        ->assertSet('newCityQuery', '');

    $city = City::query()->where('name', 'Lippstadt')->first();

    expect($city)->not->toBeNull()
        ->and($city->country_id)->toBe($this->country->id)
        ->and((float) $city->latitude)->toBe(51.6739)
        ->and((float) $city->longitude)->toBe(8.3444);
});

it('selects an existing city instead of creating a second one', function () {
    $existing = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Lippstadt',
    ]);

    Http::fake(['*' => Http::response(cityResponse())]);

    courseEventForm()
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->call('useCity', 0)
        ->assertSet('city_id', $existing->id);

    // Two rows for one town would split its events across both, and neither list
    // would be complete.
    expect(City::query()->where('name', 'Lippstadt')->count())->toBe(1);
});

it('matches an existing city regardless of case', function () {
    $existing = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'LIPPSTADT',
    ]);

    Http::fake(['*' => Http::response(cityResponse())]);

    courseEventForm()
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->call('useCity', 0)
        ->assertSet('city_id', $existing->id);

    expect(City::query()->count())->toBe(1);
});

it('ignores hits that are not populated places', function () {
    // A street named "Lippstadt" must never become a city.
    Http::fake(['*' => Http::response(cityResponse(category: 'highway'))]);

    courseEventForm()
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertCount('newCityResults', 0);

    expect(City::query()->count())->toBe(0);
});

it('demands a country before searching', function () {
    Http::fake(['*' => Http::response(cityResponse())]);

    courseEventForm()
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertHasErrors('newCityCountryId');

    Http::assertNothingSent();
});

it('demands a query long enough to mean something', function () {
    courseEventForm()
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'L')
        ->call('searchCity')
        ->assertHasErrors('newCityQuery');
});

it('narrows the geocoder query to the chosen country', function () {
    Http::fake(['*' => Http::response(cityResponse())]);

    courseEventForm()
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity');

    // Without the country filter, "Berlin" would offer the one in Maryland just as readily.
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'countrycodes=de'));
});

it('survives a geocoder outage without breaking the form', function () {
    Http::fake(['*' => Http::response('', 500)]);

    courseEventForm()
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertCount('newCityResults', 0)
        ->assertSet('newCitySearched', true)
        ->assertOk();
});

it('does nothing when handed an index that is not there', function () {
    courseEventForm()
        ->call('useCity', 99)
        ->assertOk();

    expect(City::query()->count())->toBe(0);
});
