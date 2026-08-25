<?php

/*
|--------------------------------------------------------------------------
| N10 — Schema: cities_name_unique ist weg, (osm_type, osm_id) ist unique,
| zwei Staedte OHNE OSM-Referenz koexistieren weiterhin.
|--------------------------------------------------------------------------
|
| Gegen sqlite_master statt gegen Model-Verhalten: das ist die einzige Ebene,
| die tatsaechlich beweist, dass der Index weg ist und nicht bloss, dass eine
| Anwendungsregel ihn gerade nicht ausloest.
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('has dropped cities_name_unique without a name-based successor', function () {
    $indexNames = collect(DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'cities'"))
        ->pluck('name');

    expect($indexNames)->not->toContain('cities_name_unique');

    // Kein Nachfolger auf Namensbasis: keine Definition, die (auch als Teil eines
    // zusammengesetzten Index) ausschliesslich oder zuerst auf "name" liegt.
    $sqlDefinitions = collect(DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'cities'"))
        ->pluck('sql')
        ->filter()
        ->values();

    foreach ($sqlDefinitions as $sql) {
        expect($sql)->not->toMatch('/\(\s*"?name"?\s*[,)]/i');
    }
});

it('has a unique index on (osm_type, osm_id)', function () {
    $sql = collect(DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'cities'"))
        ->pluck('sql')
        ->filter()
        ->first(fn (string $sql) => str_contains($sql, 'osm_type') && str_contains($sql, 'osm_id'));

    expect($sql)->not->toBeNull()
        ->and($sql)->toContain('UNIQUE');
});

it('rejects a second city with the same osm_type/osm_id pair', function () {
    $country = Country::factory()->create();
    City::factory()->create(['country_id' => $country->id, 'osm_type' => 'relation', 'osm_id' => 62422]);

    expect(fn () => City::factory()->create([
        'country_id' => $country->id,
        'osm_type' => 'relation',
        'osm_id' => 62422,
    ]))->toThrow(QueryException::class);
});

it('allows two cities without any OSM reference to coexist (NULLs are distinct)', function () {
    $country = Country::factory()->create();
    $creator = User::factory()->create();

    City::create([
        'name' => 'Ohne OSM Eins', 'country_id' => $country->id,
        'latitude' => 1.0, 'longitude' => 1.0, 'created_by' => $creator->id,
    ]);
    City::create([
        'name' => 'Ohne OSM Zwei', 'country_id' => $country->id,
        'latitude' => 2.0, 'longitude' => 2.0, 'created_by' => $creator->id,
    ]);

    expect(City::query()->whereNull('osm_type')->whereNull('osm_id')->count())->toBe(2);
});
