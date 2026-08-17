<?php

use App\Models\BitcoinEvent;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

function runOsmApply(): void
{
    $migration = require database_path('migrations/2026_08_17_182728_apply_verified_osm_matches_to_events.php');
    $migration->up();
}

function firstVerifiedMatch(): array
{
    return json_decode((string) file_get_contents(database_path('data/venue-osm-matches.json')), true)[0];
}

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'at']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('ships only hand-verified matches, none below the high bar', function () {
    $matches = json_decode((string) file_get_contents(database_path('data/venue-osm-matches.json')), true);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as $match) {
        expect($match['similarity'])->toBeGreaterThanOrEqual(0.85, "zu schwach: {$match['venue_name']}")
            ->and($match['osm_type'])->toBeIn(['node', 'way', 'relation'])
            ->and($match['osm_id'])->toBeInt();
    }
});

it('applies the place to the events of a matching venue', function () {
    $match = firstVerifiedMatch();

    $venue = Venue::factory()->create(['name' => $match['venue_name'], 'city_id' => $this->city->id]);
    DB::table('venues')->where('id', $venue->id)->update(['id' => $match['venue_id']]);

    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $match['venue_id'], 'course_id' => $course->id]);
    $bitcoinEvent = BitcoinEvent::factory()->create(['venue_id' => $match['venue_id']]);

    runOsmApply();

    expect($event->fresh()->osm_id)->toBe($match['osm_id'])
        ->and($event->fresh()->osm_name)->toBe($match['osm_name'])
        ->and($bitcoinEvent->fresh()->osm_id)->toBe($match['osm_id']);
});

it('refuses when the venue name no longer matches', function () {
    // Ids are stable in production but mean something else on other databases. Writing a
    // Dresden museum onto a Viennese bar would be worse than doing nothing.
    $match = firstVerifiedMatch();

    $venue = Venue::factory()->create(['name' => 'Etwas ganz anderes', 'city_id' => $this->city->id]);
    DB::table('venues')->where('id', $venue->id)->update(['id' => $match['venue_id']]);

    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $match['venue_id'], 'course_id' => $course->id]);

    runOsmApply();

    expect($event->fresh()->osm_id)->toBeNull();
});

it('never overwrites a place someone entered by hand', function () {
    $match = firstVerifiedMatch();

    $venue = Venue::factory()->create(['name' => $match['venue_name'], 'city_id' => $this->city->id]);
    DB::table('venues')->where('id', $venue->id)->update(['id' => $match['venue_id']]);

    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create([
        'venue_id' => $match['venue_id'],
        'course_id' => $course->id,
        'osm_type' => 'node',
        'osm_id' => 999999,
        'osm_name' => 'Von Hand gesetzt',
    ]);

    runOsmApply();

    expect($event->fresh()->osm_id)->toBe(999999)
        ->and($event->fresh()->osm_name)->toBe('Von Hand gesetzt');
});

it('does nothing on a database without those venues', function () {
    $course = Course::factory()->create();
    $venue = Venue::factory()->create(['name' => 'Irgendwas', 'city_id' => $this->city->id]);
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);

    runOsmApply();

    expect($event->fresh()->osm_id)->toBeNull();
});

it('is idempotent', function () {
    $match = firstVerifiedMatch();

    $venue = Venue::factory()->create(['name' => $match['venue_name'], 'city_id' => $this->city->id]);
    DB::table('venues')->where('id', $venue->id)->update(['id' => $match['venue_id']]);

    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $match['venue_id'], 'course_id' => $course->id]);

    runOsmApply();
    $after = $event->fresh()->only(['osm_type', 'osm_id', 'osm_name']);

    runOsmApply();

    expect($event->fresh()->only(['osm_type', 'osm_id', 'osm_name']))->toBe($after);
});

it('can be rolled back', function () {
    $match = firstVerifiedMatch();

    $venue = Venue::factory()->create(['name' => $match['venue_name'], 'city_id' => $this->city->id]);
    DB::table('venues')->where('id', $venue->id)->update(['id' => $match['venue_id']]);

    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $match['venue_id'], 'course_id' => $course->id]);

    runOsmApply();
    expect($event->fresh()->osm_id)->not->toBeNull();

    $migration = require database_path('migrations/2026_08_17_182728_apply_verified_osm_matches_to_events.php');
    $migration->down();

    expect($event->fresh()->osm_id)->toBeNull();
});
