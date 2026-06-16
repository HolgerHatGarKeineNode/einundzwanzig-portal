<?php

use App\Models\BitcoinEvent;
use App\Models\City;
use App\Models\CourseEvent;
use App\Models\Meetup;
use App\Models\Venue;

it('does nothing on a dry-run', function () {
    $venue = Venue::factory()->create();

    $this->artisan('places:cleanup')->assertExitCode(0);

    expect(Venue::query()->find($venue->id))->not->toBeNull();
    expect(City::query()->find($venue->city_id))->not->toBeNull();
});

it('deletes unused venues and cities with --force', function () {
    $venue = Venue::factory()->create();
    $city = City::query()->find($venue->city_id);

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation('This permanently deletes venues and cities on this database. Continue?', 'yes')
        ->assertExitCode(0);

    expect(Venue::query()->find($venue->id))->toBeNull();
    expect(City::query()->find($city->id))->toBeNull();
});

it('keeps venues with course events and bitcoin events', function () {
    $withCourse = CourseEvent::factory()->create()->venue;
    $withBitcoin = BitcoinEvent::factory()->create()->venue;

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation('This permanently deletes venues and cities on this database. Continue?', 'yes')
        ->assertExitCode(0);

    expect(Venue::query()->find($withCourse->id))->not->toBeNull();
    expect(Venue::query()->find($withBitcoin->id))->not->toBeNull();
});

it('keeps cities with meetups even without venues', function () {
    $meetup = Meetup::factory()->create();

    $this->artisan('places:cleanup', ['--force' => true])
        ->expectsConfirmation('This permanently deletes venues and cities on this database. Continue?', 'yes')
        ->assertExitCode(0);

    expect(City::query()->find($meetup->city_id))->not->toBeNull();
});
