<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Services\Osm\NominatimClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Meetup add-city searches Nominatim. Organisers never type WGS84 — the four
 * onboarding traps in issue #148 (no lookup, paste-concat, locale comma, step)
 * all came from type=number coordinate fields. Coordinates and osm_* come from
 * the hit. Outage is not an empty result list.
 */
beforeEach(function () {
    NominatimClient::resetThrottle();
    Cache::flush();
    app()->bind(NominatimClient::class, fn (): NominatimClient => new NominatimClient(minIntervalMs: 0));

    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    actingAsUser();
});

dataset('meetup add-city forms', [
    'create' => ['meetups.create'],
    'edit' => ['meetups.edit'],
]);

function meetupPlaceHit(string $name = 'Lippstadt', string $category = 'place', int $osmId = 123456): array
{
    return [
        'osm_type' => 'relation',
        'osm_id' => $osmId,
        'name' => $name,
        'display_name' => "{$name}, Kreis Soest, Nordrhein-Westfalen, Deutschland",
        'lat' => '51.6739',
        'lon' => '8.3444',
        'category' => $category,
    ];
}

function meetupAddCityForm(string $component)
{
    if ($component === 'meetups.edit') {
        $meetup = Meetup::factory()->create(['created_by' => auth()->id()]);

        return Livewire::test('meetups.edit', ['meetup' => $meetup]);
    }

    return Livewire::test('meetups.create');
}

it('creates the city from a search result and selects it with coordinates from the hit', function (string $component) {
    Http::fake(['*' => Http::response([meetupPlaceHit()])]);

    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertCount('newCityResults', 1)
        ->call('chooseCity', 0)
        ->assertSet('newCityQuery', '');

    $city = City::query()->where('name', 'Lippstadt')->first();

    expect($city)->not->toBeNull()
        ->and($form->get('city_id'))->toBe($city->id)
        ->and($city->country_id)->toBe($this->country->id)
        ->and((float) $city->latitude)->toBe(51.6739)
        ->and((float) $city->longitude)->toBe(8.3444)
        ->and($city->osm_type)->toBe('relation')
        ->and($city->osm_id)->toBe(123456)
        ->and((float) $city->osm_lat)->toBe(51.6739)
        ->and((float) $city->osm_lon)->toBe(8.3444);

    expect($form->html())
        ->not->toContain('wire:model="newCityLatitude"')
        ->not->toContain('wire:model="newCityLongitude"')
        ->not->toContain('newCityLatitude')
        ->not->toContain('newCityLongitude');
})->with('meetup add-city forms');

it('selects an existing city with the same osm id instead of duplicating it', function (string $component) {
    $existing = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Alt-Lippstadt',
        'osm_type' => 'relation',
        'osm_id' => 123456,
    ]);

    Http::fake(['*' => Http::response([meetupPlaceHit()])]);

    meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->call('chooseCity', 0)
        ->assertSet('city_id', $existing->id);

    expect(City::query()->where('osm_type', 'relation')->where('osm_id', 123456)->count())->toBe(1);
})->with('meetup add-city forms');

it('requires confirmDuplicateCity for a same-name hit without a matching osm id', function (string $component) {
    City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Lippstadt',
    ]);

    Http::fake(['*' => Http::response([meetupPlaceHit()])]);

    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity');

    $cityIdBeforePick = $form->get('city_id');

    $form->call('chooseCity', 0);

    expect($form->get('city_id'))->toBe($cityIdBeforePick)
        ->and($form->get('confirmDuplicateCity'))->toBeFalse()
        ->and($form->get('duplicateCityCandidates'))->not->toBeEmpty()
        ->and(City::query()->where('name', 'Lippstadt')->count())->toBe(1);

    $form->set('confirmDuplicateCity', true)
        ->call('chooseCity', 0);

    $created = City::query()->where('name', 'Lippstadt')->orderByDesc('id')->first();

    expect(City::query()->where('name', 'Lippstadt')->count())->toBe(2)
        ->and($form->get('city_id'))->toBe($created->id);
})->with('meetup add-city forms');

it('discards highway category hits so a street never becomes a city', function (string $component) {
    Http::fake(['*' => Http::response([meetupPlaceHit(category: 'highway')])]);

    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertCount('newCityResults', 0)
        ->assertSet('newCityFailed', false)
        ->assertSet('newCitySearched', true);

    expect($form->html())
        ->toContain('data-testid="add-city-empty"')
        ->not->toContain('data-testid="add-city-unavailable"');

    expect(City::query()->where('name', 'Lippstadt')->count())->toBe(0);
})->with('meetup add-city forms');

it('demands a country before searching and does not call Nominatim', function (string $component) {
    Http::fake(['*' => Http::response([meetupPlaceHit()])]);

    meetupAddCityForm($component)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertHasErrors('newCityCountryId');

    Http::assertNothingSent();
})->with('meetup add-city forms');

it('rejects a 1-character query before calling Nominatim', function (string $component) {
    Http::fake(['*' => Http::response([meetupPlaceHit()])]);

    meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'L')
        ->call('searchCity')
        ->assertHasErrors('newCityQuery');

    Http::assertNothingSent();
})->with('meetup add-city forms');

it('rejects a 2-character query with a validation error, not the empty callout', function (string $component) {
    Http::fake(['*' => Http::response([meetupPlaceHit()])]);

    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Li')
        ->call('searchCity')
        ->assertHasErrors('newCityQuery')
        ->assertSet('newCitySearched', false);

    expect($form->html())->not->toContain('data-testid="add-city-empty"');

    Http::assertNothingSent();
})->with('meetup add-city forms');

it('searches when the query is 3 characters', function (string $component) {
    Http::fake(['*' => Http::response([meetupPlaceHit('Lin')])]);

    meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lin')
        ->call('searchCity')
        ->assertHasNoErrors()
        ->assertCount('newCityResults', 1);

    Http::assertSent(fn ($request): bool => ($request->data()['q'] ?? null) === 'Lin');
})->with('meetup add-city forms');

it('narrows the geocoder query to the chosen country', function (string $component) {
    Http::fake(['*' => Http::response([meetupPlaceHit()])]);

    meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'countrycodes=de'));
})->with('meetup add-city forms');

it('shows the unavailable callout when Nominatim returns 500, not the empty callout', function (string $component) {
    Http::fake(['*' => Http::response('', 500)]);

    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertCount('newCityResults', 0)
        ->assertSet('newCityFailed', true)
        ->assertSet('newCitySearched', true)
        ->assertOk();

    expect($form->html())
        ->toContain('data-testid="add-city-unavailable"')
        ->not->toContain('data-testid="add-city-empty"');
})->with('meetup add-city forms');

it('shows the unavailable callout when Nominatim connection dies, not the empty callout', function (string $component) {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertCount('newCityResults', 0)
        ->assertSet('newCityFailed', true)
        ->assertOk();

    expect($form->html())
        ->toContain('data-testid="add-city-unavailable"')
        ->not->toContain('data-testid="add-city-empty"');
})->with('meetup add-city forms');

it('shows the empty callout when Nominatim returns zero place hits', function (string $component) {
    Http::fake(['*' => Http::response([])]);

    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertCount('newCityResults', 0)
        ->assertSet('newCityFailed', false)
        ->assertSet('newCitySearched', true)
        ->assertOk();

    expect($form->html())
        ->toContain('data-testid="add-city-empty"')
        ->not->toContain('data-testid="add-city-unavailable"');
})->with('meetup add-city forms');

it('does not cache a Nominatim 500 so a second search still hits the network', function (string $component) {
    Http::fake([
        '*' => Http::sequence()
            ->push('', 500)
            ->push([meetupPlaceHit()]),
    ]);

    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id)
        ->set('newCityQuery', 'Lippstadt')
        ->call('searchCity')
        ->assertSet('newCityFailed', true);

    $form->call('searchCity')
        ->assertSet('newCityFailed', false)
        ->assertCount('newCityResults', 1);

    Http::assertSentCount(2);
})->with('meetup add-city forms');

it('does nothing when handed an index that is not there', function (string $component) {
    meetupAddCityForm($component)
        ->call('chooseCity', 99)
        ->assertOk();

    expect(City::query()->where('name', 'Lippstadt')->count())->toBe(0);
})->with('meetup add-city forms');

it('rejects a forged Nominatim hit on the locked results list and does not create it', function (string $component) {
    $form = meetupAddCityForm($component)
        ->set('newCityCountryId', $this->country->id);

    $citiesBefore = City::query()->count();

    expect(fn () => $form->set('newCityResults', [[
        'osm_type' => 'relation',
        'osm_id' => 62422,
        'osm_name' => 'Forgedstadt',
        'osm_lat' => 1,
        'osm_lon' => 2,
        'category' => 'place',
    ]]))->toThrow(CannotUpdateLockedPropertyException::class);

    expect($form->get('newCityResults'))->toBe([])
        ->and(City::query()->count())->toBe($citiesBefore);

    $form->call('chooseCity', 0);

    expect(City::query()->count())->toBe($citiesBefore)
        ->and(City::query()->where('osm_id', 62422)->exists())->toBeFalse()
        ->and(City::query()->where('name', 'Forgedstadt')->exists())->toBeFalse();
})->with('meetup add-city forms');
