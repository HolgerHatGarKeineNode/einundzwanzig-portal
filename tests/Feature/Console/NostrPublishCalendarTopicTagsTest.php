<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Region;
use App\Support\NostrEventTransmitter;
use swentel\nostr\Event\Event;

/*
 * Issue #69, end to end: what the relays actually receive.
 *
 * `NostrCalendarTopicTagsTest` pins the factory in isolation. This file asks the other
 * half of the question — does the command that signs and transmits carry those tags
 * out? — by mocking `NostrEventTransmitter::transmit` and reading the event that was
 * handed to it, the technique issue #76 established in
 * `NostrPublishUppercaseCountryCodeTest`.
 */

const TOPIC_TAG_TEST_KEY = '7c1d3e5f7a9b1c3d5e7f9a1b3c5d7e9f1a3b5c7d9e1f3a5b7c9d1e3f5a7b9c1d';

/**
 * A meetup in Indianapolis, Indiana, US — the address from the issue.
 */
function topicTagIndianapolisMeetup(array $attributes = []): Meetup
{
    $country = Country::factory()->create(['code' => 'us']);
    $region = Region::factory()->indiana()->create(['country_id' => $country->id]);

    $city = City::factory()->create([
        'country_id' => $country->id,
        'region_id' => $region->id,
        'name' => 'Indianapolis',
        'latitude' => 39.7684,
        'longitude' => -86.1581,
    ]);

    return Meetup::factory()->create(array_merge([
        'city_id' => $city->id,
        'nostr_publishing_enabled' => true,
    ], $attributes));
}

/**
 * @return list<string>
 */
function topicTagValuesOf(?Event $event): array
{
    return array_map(static fn (array $tag): string => $tag[1], $event?->getTag('t') ?? []);
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => TOPIC_TAG_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

function topicTagCaptureTransmitted(&$captured): void
{
    test()->mock(NostrEventTransmitter::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('transmit')
            ->once()
            ->andReturnUsing(function (Event $event, array $relayUrls) use (&$captured) {
                $captured = $event;

                return true;
            });
    });
}

it('transmits the geography topics on a published calendar', function () {
    $transmitted = null;
    topicTagCaptureTransmitted($transmitted);

    topicTagIndianapolisMeetup();

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->assertExitCode(0);

    expect($transmitted?->getKind())->toBe(31924)
        ->and(topicTagValuesOf($transmitted))->toBe([
            'bitcoin', 'meetup', 'indianapolis', 'indiana', 'in-us', 'in', 'us',
        ]);
});

it('transmits the geography topics on a published time-based event', function () {
    $transmitted = null;
    topicTagCaptureTransmitted($transmitted);

    $meetup = topicTagIndianapolisMeetup();
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->assertExitCode(0);

    expect($transmitted?->getKind())->toBe(31923)
        ->and(topicTagValuesOf($transmitted))->toBe([
            'bitcoin', 'meetup', 'indianapolis', 'indiana', 'in-us', 'in', 'us',
        ]);
});
