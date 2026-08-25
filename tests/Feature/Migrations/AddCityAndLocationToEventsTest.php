<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * This migration reads a table that a later migration drops, so on a fully migrated database
 * neither `venues` nor the `venue_id` columns exist any more. The test rebuilds just enough
 * of that vanished world to exercise the copy — which is worth doing, because the whole step
 * exists to stop 191 events from losing their address.
 *
 * Seit P7 gilt dasselbe fuer die zweite Tabelle: `bitcoin_events` ist gedroppt, die Faelle
 * laufen deshalb ueber `course_events`. Der Mechanismus ist derselbe — die Migration
 * behandelt beide Tabellen in derselben Schleife.
 */
function withVenueWorld(array $venue, callable $test): void
{
    Schema::create('venues', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('city_id');
        $table->string('name');
        $table->string('street')->nullable();
    });

    Schema::table('course_events', fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('venue_id')->nullable());

    $venueId = DB::table('venues')->insertGetId($venue);

    try {
        $test($venueId);
    } finally {
        Schema::table('course_events', fn (Blueprint $blueprint) => $blueprint->dropColumn('venue_id'));

        Schema::drop('venues');
    }
}

function runCityAndLocationCopy(): void
{
    $migration = require database_path('migrations/2026_08_17_190319_add_city_and_location_to_course_and_bitcoin_events.php');
    $migration->up();
}

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('gives the surviving event table a city and a location column', function () {
    expect(Schema::hasColumn('course_events', 'city_id'))->toBeTrue('course_events.city_id fehlt')
        ->and(Schema::hasColumn('course_events', 'location'))->toBeTrue('course_events.location fehlt');
});

/**
 * Die zweite Tabelle, die diese Migration einst mitbediente, ist fort — und die Migration
 * darf trotzdem noch laufen. Genau daran haengt dieser ganze Test: `runCityAndLocationCopy()`
 * ruft `up()` auf einer durchmigrierten Datenbank auf.
 */
it('runs at all although bitcoin_events no longer exists', function () {
    expect(Schema::hasTable('bitcoin_events'))->toBeFalse();

    runCityAndLocationCopy(); // wirft nicht

    expect(Schema::hasColumn('course_events', 'location'))->toBeTrue();
});

it('carries name and street of the venue into the event', function () {
    withVenueWorld([
        'city_id' => $this->city->id,
        'name' => 'Bürgerhaus Neumarkt Oberpfalz',
        'street' => 'Fischergasse 1 92318 Neumarkt',
    ], function (int $venueId) {
        $event = CourseEvent::factory()->create([
            'course_id' => Course::factory()->create()->id,
            'city_id' => null,
            'location' => null,
        ]);
        DB::table('course_events')->where('id', $event->id)->update(['venue_id' => $venueId]);

        runCityAndLocationCopy();

        expect($event->fresh())
            ->location->toBe('Bürgerhaus Neumarkt Oberpfalz, Fischergasse 1 92318 Neumarkt')
            ->city_id->toBe($this->city->id);
    });
});

it('does not repeat the name when the street merely echoes it', function () {
    // Real production row: a venue that was never an address in the first place.
    withVenueWorld([
        'city_id' => $this->city->id,
        'name' => 'Wunschort im Rhein-Neckar Kreis',
        'street' => 'Wunschort im Rhein-Neckar Kreis',
    ], function (int $venueId) {
        $event = CourseEvent::factory()->create(['location' => null]);
        DB::table('course_events')->where('id', $event->id)->update(['venue_id' => $venueId]);

        runCityAndLocationCopy();

        expect($event->fresh()->location)->toBe('Wunschort im Rhein-Neckar Kreis');
    });
});

it('copes with a venue whose street is blank', function () {
    withVenueWorld([
        'city_id' => $this->city->id,
        'name' => 'Nur ein Name',
        'street' => '',
    ], function (int $venueId) {
        $event = CourseEvent::factory()->create(['location' => null]);
        DB::table('course_events')->where('id', $event->id)->update(['venue_id' => $venueId]);

        runCityAndLocationCopy();

        expect($event->fresh()->location)->toBe('Nur ein Name');
    });
});

it('never overwrites a location somebody entered by hand', function () {
    withVenueWorld([
        'city_id' => $this->city->id,
        'name' => 'Alter Venue-Name',
        'street' => 'Alte Straße 1',
    ], function (int $venueId) {
        $event = CourseEvent::factory()->create([
            'location' => 'Vom Veranstalter korrigiert: Hinterhof, Eingang B',
        ]);
        DB::table('course_events')->where('id', $event->id)->update(['venue_id' => $venueId]);

        runCityAndLocationCopy();

        expect($event->fresh()->location)->toBe('Vom Veranstalter korrigiert: Hinterhof, Eingang B');
    });
});

it('runs twice without changing anything the second time', function () {
    withVenueWorld([
        'city_id' => $this->city->id,
        'name' => 'Doppelt hält besser',
        'street' => 'Teststraße 2',
    ], function (int $venueId) {
        $event = CourseEvent::factory()->create(['location' => null]);
        DB::table('course_events')->where('id', $event->id)->update(['venue_id' => $venueId]);

        runCityAndLocationCopy();
        $afterFirst = $event->fresh()->location;
        runCityAndLocationCopy();

        expect($event->fresh()->location)->toBe($afterFirst);
    });
});

it('truncates an overlong composition rather than failing the column', function () {
    withVenueWorld([
        'city_id' => $this->city->id,
        'name' => str_repeat('a', 200),
        'street' => str_repeat('b', 200),
    ], function (int $venueId) {
        $event = CourseEvent::factory()->create(['location' => null]);
        DB::table('course_events')->where('id', $event->id)->update(['venue_id' => $venueId]);

        runCityAndLocationCopy();

        expect(mb_strlen((string) $event->fresh()->location))->toBeLessThanOrEqual(255);
    });
});

it('leaves events alone that never had a venue', function () {
    $event = CourseEvent::factory()->create(['location' => 'Schon gesetzt']);

    runCityAndLocationCopy();

    expect($event->fresh()->location)->toBe('Schon gesetzt');
});
