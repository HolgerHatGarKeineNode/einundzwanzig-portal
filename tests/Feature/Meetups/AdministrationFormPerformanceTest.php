<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Issue 657eb9fb: organiser forms and the events list were "noticeably slow to
 * open". These two tests pin the two concrete causes found by reading the code —
 * not every slowdown is a query-count problem, but these two were.
 */
it('loads the meetup creation form without selecting the heavy city columns', function () {
    $country = Country::factory()->create();
    // 50 cities is a realistic mid-size country's worth on this portal (production
    // carries roughly 300 across all countries) — enough to make a per-row SELECT *
    // difference show up in the query log rather than needing thousands of rows.
    City::factory()->count(50)->create([
        'country_id' => $country->id,
        // Representative of a real OSM boundary payload, not the empty default the
        // factory would otherwise leave in place — the column this fix stops
        // selecting only matters once it is actually sized.
        'simplified_geojson' => ['type' => 'Polygon', 'coordinates' => [array_fill(0, 500, [13.4, 52.5])]],
    ]);

    actingAsUser();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    Livewire::test('meetups.create')->assertStatus(200);

    $cityQuery = collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'from "cities"'));

    // "select *" carries no column names to grep for — the query has to be checked
    // for an explicit column list instead, or this assertion would pass either way.
    expect($cityQuery)->not->toBeNull()
        ->and($cityQuery)->not->toContain('select *')
        ->and($cityQuery)->toContain('"id"')
        ->and($cityQuery)->toContain('"name"');
});

it('opens the events list without re-querying leader status once per event', function () {
    $leader = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $leader->id]);
    MeetupEvent::factory()->count(8)->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])->assertStatus(200);

    $leaderChecks = collect($queries)->filter(
        fn (string $sql): bool => str_contains($sql, 'from "meetup_user"') && str_contains($sql, '"is_leader"')
    )->count();

    // Before the fix: one per event card PLUS one for the page-level "create event"
    // button — 9 for 8 events. leadByMe is a boolean accessor, and Eloquent only
    // caches object-returning ones by default, so every read re-ran the query.
    expect($leaderChecks)->toBe(1);
});
