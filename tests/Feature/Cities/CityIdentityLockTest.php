<?php

/*
|--------------------------------------------------------------------------
| Vier Korrekturen aus zwei Pruefberichten, die nach dem Zwei-Abilities-
| Vertrag aus Issue #30 dazukamen (Stand 6d176fd):
|
|  K1 — updatedOsmPlace() befuellt population nur noch fuer canEditIdentity.
|  K2 — City::identityChanges() wertet einen Nicht-Skalar fail-closed als
|       Aenderung, statt an "(string) $array" mit einer 500 zu sterben.
|  K3 — Die Sperre im Formular ist readonly + aria-describedby, nicht
|       disabled; Land/Region werden fuer Gesperrte als Text gerendert.
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
});

/*
|--------------------------------------------------------------------------
| K1 — Der OSM-Picker fuellt kein gesperrtes Feld mehr
|--------------------------------------------------------------------------
*/

it('leaves population untouched for a stranger who picks an osm place with a population, and still saves', function () {
    $creator = User::factory()->create();
    $city = City::factory()->create([
        'country_id' => $this->country->id,
        'created_by' => $creator->id,
        'name' => 'Ungeared City',
        'population' => null,
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test('cities.edit', ['city' => $city])
        ->set('osmPlace', ['osm_id' => 62422, 'osm_lat' => 52.5, 'osm_lon' => 13.4, 'population' => 3769962])
        ->assertSet('population', null)
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($city->fresh())->population->toBeNull();
});

it('still prefills population from the osm place for the creator', function () {
    $creator = User::factory()->create();
    $city = City::factory()->create([
        'country_id' => $this->country->id,
        'created_by' => $creator->id,
        'name' => 'Owned City',
        'population' => null,
    ]);

    $this->actingAs($creator);

    Livewire::test('cities.edit', ['city' => $city])
        ->set('osmPlace', ['osm_id' => 62422, 'osm_lat' => 52.5, 'osm_lon' => 13.4, 'population' => 3769962])
        ->assertSet('population', 3769962);
});

/*
|--------------------------------------------------------------------------
| K2 — Ein Nicht-Skalar in einem Identitaetsfeld ergibt 403/422, nicht 500
|--------------------------------------------------------------------------
*/

it('rejects an array in an identity field with 403 rather than 500 for a non-owner', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id, 'country_id' => $this->country->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/cities/{$city->id}", ['name' => ['x']]);

    $response->assertForbidden();
});

it('rejects an object in an identity field with 403 rather than 500 for a non-owner', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id, 'country_id' => $this->country->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/cities/{$city->id}", ['name' => ['foo' => 'bar']]);

    $response->assertForbidden();
});

it('rejects an array in an identity field with a validation error rather than 500 for the owner', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id, 'country_id' => $this->country->id]);

    Sanctum::actingAs($owner);

    $response = $this->patchJson("/api/cities/{$city->id}", ['name' => ['x']]);

    expect($response->status())->not->toBe(500);
    $response->assertStatus(422);
});

it('rejects an object in an identity field with a validation error rather than 500 for the owner', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id, 'country_id' => $this->country->id]);

    Sanctum::actingAs($owner);

    $response = $this->patchJson("/api/cities/{$city->id}", ['name' => ['foo' => 'bar']]);

    expect($response->status())->not->toBe(500);
    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| K3 — Die Sperre ist erreichbar (readonly) statt ausgegraut (disabled)
|--------------------------------------------------------------------------
*/

it('renders the identity fields as readonly with a lock and a callout link, never as disabled, when locked', function () {
    $creator = User::factory()->create();
    $region = Region::factory()->create(['country_id' => $this->country->id]);
    $city = City::factory()->create([
        'country_id' => $this->country->id,
        'region_id' => $region->id,
        'created_by' => $creator->id,
        'name' => 'Locked City',
    ]);

    $this->actingAs(User::factory()->create());

    $component = Livewire::test('cities.edit', ['city' => $city]);
    $html = $component->html();

    // Die drei Identitaetsfelder tragen readonly...
    expect(substr_count($html, 'readonly="readonly"'))->toBe(3)
        // ...und niemals disabled="..." — Flux graut damit sowohl den Kontrast als
        // auch jeden Tab-Stopp weg.
        ->and($html)->not->toContain('disabled="disabled"')
        // Der Callout traegt die id, auf die aria-describedby zeigt.
        ->and(substr_count($html, 'id="identity-lock"'))->toBe(1)
        ->and(substr_count($html, 'aria-describedby="identity-lock"'))->toBe(5)
        // Land und Region stehen als Text, nicht als (totes) Select.
        ->and($html)->not->toContain('data-flux-listbox')
        ->and($html)->toContain('Locked City');

    $component->assertSet('country_id', $this->country->id)
        ->assertSet('region_id', $region->id);
});

it('renders country and region as selects without readonly or the lock callout when unlocked', function () {
    $creator = User::factory()->create();
    $city = City::factory()->create([
        'country_id' => $this->country->id,
        'created_by' => $creator->id,
        'name' => 'Open City',
    ]);

    $this->actingAs($creator);

    $html = Livewire::test('cities.edit', ['city' => $city])->html();

    expect($html)->not->toContain('readonly="readonly"')
        ->and($html)->not->toContain('id="identity-lock"')
        ->and($html)->toContain('data-flux-listbox');
});

/*
|--------------------------------------------------------------------------
| Escaping & Uebersetzungsparitaet
|--------------------------------------------------------------------------
*/

it('never double-escapes html entities and never mentions wikidata or wikipedia fields in the edit form', function () {
    $creator = User::factory()->create();
    $city = City::factory()->create(['country_id' => $this->country->id, 'created_by' => $creator->id]);

    $this->actingAs(User::factory()->create());

    $html = Livewire::test('cities.edit', ['city' => $city])->html();

    expect($html)->not->toContain('&amp;#039;')
        ->and($html)->not->toContain('&amp;amp;')
        ->and($html)->not->toContain('Wikidata')
        ->and($html)->not->toContain('Wikipedia');
});
