<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrEventTransmitter;
use swentel\nostr\Key\Key;

const TEST_PRIVATE_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

function meetupWithCity(array $meetupAttrs = []): Meetup
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => 'de'])->id,
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ]);

    return Meetup::factory()->create(array_merge(['city_id' => $city->id], $meetupAttrs));
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => TEST_PRIVATE_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

it('fails when no publisher key is configured', function () {
    config(['services.nostr.publisher_key' => null]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->assertExitCode(1);
});

it('fails for an unsupported model', function () {
    $this->artisan('nostr:publish-calendar', ['--model' => 'Course'])
        ->assertExitCode(1);
});

it('succeeds without changes when there is nothing to publish', function () {
    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->assertExitCode(0);
});

it('publishes a meetup as a kind 31924 calendar and stores its coordinate', function () {
    $this->mock(NostrEventTransmitter::class, function ($mock) {
        $mock->shouldReceive('transmit')->once()->andReturn(true);
    });

    $meetup = meetupWithCity();

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->assertExitCode(0);

    $pubkey = (new Key)->getPublicKey(TEST_PRIVATE_KEY);

    expect($meetup->refresh()->nostr_coordinate)->toBe("31924:{$pubkey}:meetup-{$meetup->id}");
});

it('publishes a meetup event as a kind 31923 event and stores its coordinate', function () {
    $this->mock(NostrEventTransmitter::class, function ($mock) {
        $mock->shouldReceive('transmit')->once()->andReturn(true);
    });

    $meetup = meetupWithCity();
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->assertExitCode(0);

    $pubkey = (new Key)->getPublicKey(TEST_PRIVATE_KEY);

    expect($meetupEvent->refresh()->nostr_coordinate)->toBe("31923:{$pubkey}:meetup-event-{$meetupEvent->id}");
});

it('does not touch already-published meetups', function () {
    $this->mock(NostrEventTransmitter::class, function ($mock) {
        $mock->shouldNotReceive('transmit');
    });

    $meetup = meetupWithCity(['nostr_coordinate' => '31924:abc:meetup-existing']);

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->assertExitCode(0);

    expect($meetup->refresh()->nostr_coordinate)->toBe('31924:abc:meetup-existing');
});

it('ignores meetup events that already started', function () {
    $this->mock(NostrEventTransmitter::class, function ($mock) {
        $mock->shouldNotReceive('transmit');
    });

    $meetup = meetupWithCity();
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->subDay(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->assertExitCode(0);
});

it('leaves nostr_coordinate empty when no relay accepts the event', function () {
    $this->mock(NostrEventTransmitter::class, function ($mock) {
        $mock->shouldReceive('transmit')->once()->andReturn(false);
    });

    $meetup = meetupWithCity();

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->assertExitCode(1);

    expect($meetup->refresh()->nostr_coordinate)->toBeNull();
});
