<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\Schema;

/*
 * Issue #72: a missing column must stop a publishing command, not look like success.
 *
 * SQLite degrades a double-quoted identifier that matches no column into a STRING
 * LITERAL instead of raising an error, and Laravel quotes identifiers with double
 * quotes. So on a database whose gating migration has not run,
 * `where "nostr_coordinate" is null` compares the constant string 'nostr_coordinate'
 * against NULL — never true. The command then finds nothing to publish, prints
 * "No unpublished items" and exits 0, which is indistinguishable from a healthy,
 * caught-up system. `DB_CONNECTION` defaults to sqlite in config/database.php and
 * .env.example, so this is not confined to the test suite.
 *
 * The first test below reproduces the degradation itself; the rest assert that each
 * command now exits non-zero and names the column it is missing.
 */

const SCHEMA_GUARD_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

function schemaGuardMeetup(array $attributes = []): Meetup
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => 'de'])->id,
    ]);

    return Meetup::factory()->create(array_merge(['city_id' => $city->id], $attributes));
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => SCHEMA_GUARD_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

it('degrades whereNull to a never-true comparison when the column is missing', function () {
    schemaGuardMeetup(['nostr_publishing_enabled' => true]);

    Schema::table('meetups', fn ($table) => $table->dropColumn('nostr_coordinate'));

    expect(Meetup::query()->whereNull('nostr_coordinate')->count())->toBe(0)
        ->and(Meetup::query()->whereNotNull('nostr_coordinate')->count())->toBe(1);
});

it('fails and names the column when meetups.nostr_coordinate is missing', function () {
    schemaGuardMeetup(['nostr_publishing_enabled' => true]);

    Schema::table('meetups', fn ($table) => $table->dropColumn('nostr_coordinate'));

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->expectsOutputToContain('meetups.nostr_coordinate')
        ->assertExitCode(1);
});

it('fails and names the column when meetups.nostr_publishing_enabled is missing', function () {
    schemaGuardMeetup();

    Schema::table('meetups', fn ($table) => $table->dropColumn('nostr_publishing_enabled'));

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->expectsOutputToContain('meetups.nostr_publishing_enabled')
        ->assertExitCode(1);
});

it('fails and names the column when meetup_events.nostr_coordinate is missing', function () {
    $meetup = schemaGuardMeetup(['nostr_publishing_enabled' => true]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    Schema::table('meetup_events', fn ($table) => $table->dropColumn('nostr_coordinate'));

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('meetup_events.nostr_coordinate')
        ->assertExitCode(1);
});

it('fails and names the column when meetups.nostr_status is missing', function () {
    schemaGuardMeetup();

    Schema::table('meetups', fn ($table) => $table->dropColumn('nostr_status'));

    $this->artisan('nostr:publish', ['--model' => 'Meetup'])
        ->expectsOutputToContain('meetups.nostr_status')
        ->assertExitCode(1);
});

it('fails and names the column when course_events.nostr_status is missing', function () {
    Schema::table('course_events', fn ($table) => $table->dropColumn('nostr_status'));

    $this->artisan('nostr:publish', ['--model' => 'CourseEvent'])
        ->expectsOutputToContain('course_events.nostr_status')
        ->assertExitCode(1);
});

it('still reports an empty result set when the schema is complete', function () {
    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->expectsOutputToContain('No unpublished items')
        ->assertExitCode(0);

    $this->artisan('nostr:publish', ['--model' => 'Meetup'])
        ->expectsOutputToContain('No unpublished items')
        ->assertExitCode(0);
});
