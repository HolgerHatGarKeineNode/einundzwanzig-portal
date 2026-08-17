<?php

use App\Models\BitcoinEvent;
use App\Models\City;
use App\Models\CourseEvent;
use App\Models\Meetup;

const CLEANUP_CONFIRMATION = 'This permanently deletes cities on this database. Continue?';

it('does nothing on a dry-run', function () {
    $city = City::factory()->create();

    $this->artisan('places:cleanup')->assertExitCode(0);

    expect(City::query()->find($city->id))->not->toBeNull();
});

it('deletes a city nothing points at with --force', function () {
    $city = City::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation(CLEANUP_CONFIRMATION, 'yes')
        ->assertExitCode(0);

    expect(City::query()->find($city->id))->toBeNull();
});

it('keeps a city that still hosts course events', function () {
    $event = CourseEvent::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation(CLEANUP_CONFIRMATION, 'yes')
        ->assertExitCode(0);

    expect(City::query()->find($event->city_id))->not->toBeNull();
});

it('keeps a city that still hosts bitcoin events', function () {
    $event = BitcoinEvent::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation(CLEANUP_CONFIRMATION, 'yes')
        ->assertExitCode(0);

    expect(City::query()->find($event->city_id))->not->toBeNull();
});

it('keeps a city that still hosts meetups', function () {
    $meetup = Meetup::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation(CLEANUP_CONFIRMATION, 'yes')
        ->assertExitCode(0);

    expect(City::query()->find($meetup->city_id))->not->toBeNull();
});

it('aborts without deleting when the confirmation is declined', function () {
    $city = City::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation(CLEANUP_CONFIRMATION, 'no')
        ->assertExitCode(1);

    expect(City::query()->find($city->id))->not->toBeNull();
});
