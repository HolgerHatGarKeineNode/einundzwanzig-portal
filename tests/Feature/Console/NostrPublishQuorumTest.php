<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrEventTransmitter;
use Tests\Fixtures\RecordingNostrTransmitter;

/**
 * "One relay is enough" — the defect behind 557 of 684 upcoming dates that were marked
 * published on 2026-11-17 while neither configured relay held them.
 *
 * A record is published when NostrRelays::quorum() relays answered `OK true`, not when
 * one of them did.
 */
const QUORUM_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

function quorumDate(): MeetupEvent
{
    $city = City::factory()->create(['country_id' => Country::factory()->create(['code' => 'de'])->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'nostr_publishing_enabled' => true]);

    return MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addWeek()]);
}

function quorumTransmitter(array $refusingRelays): RecordingNostrTransmitter
{
    $transmitter = new RecordingNostrTransmitter;
    $transmitter->refusingRelays = $refusingRelays;
    app()->instance(NostrEventTransmitter::class, $transmitter);

    return $transmitter;
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => QUORUM_TEST_KEY,
        'services.nostr.relays' => ['wss://a.relay.test', 'wss://b.relay.test', 'wss://c.relay.test'],
        'services.nostr.publish_quorum' => 2,
    ]);
});

it('does not mark a date published when only one of three relays accepted it', function () {
    quorumTransmitter([
        'wss://b.relay.test' => 'rate-limited: slow down',
        'wss://c.relay.test' => 'blocked: pubkey not admitted',
    ]);

    $date = quorumDate();

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        // The refusal text of each relay reaches the operator instead of being dropped.
        ->expectsOutputToContain('wss://b.relay.test: rate-limited: slow down')
        ->assertExitCode(1);

    expect($date->refresh()->nostr_coordinate)->toBeNull()
        ->and((int) $date->nostr_publish_attempts)->toBe(1);
});

it('marks a date published when two of three relays accepted it', function () {
    $transmitter = quorumTransmitter(['wss://c.relay.test' => 'blocked: pubkey not admitted']);

    $date = quorumDate();

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])->assertExitCode(0);

    expect($date->refresh()->nostr_coordinate)->not->toBeNull()
        // Offered to every configured relay, the refusing one included.
        ->and($transmitter->relayLists[0])->toBe(['wss://a.relay.test', 'wss://b.relay.test', 'wss://c.relay.test']);
});

it('never asks for more relays than are configured', function () {
    config(['services.nostr.relays' => ['wss://a.relay.test'], 'services.nostr.publish_quorum' => 2]);
    quorumTransmitter([]);

    $date = quorumDate();

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])->assertExitCode(0);

    expect($date->refresh()->nostr_coordinate)->not->toBeNull();
});
