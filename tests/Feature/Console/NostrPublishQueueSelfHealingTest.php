<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrEventTransmitter;
use App\Support\NostrPublishFailures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use swentel\nostr\Event\Event;

/*
 * One record must not be able to hold the publishing queue.
 *
 * `nostr:publish-calendar` ends its run at the first rejected transmission — right for
 * a relay having a bad minute, and a block for a payload the relays will never accept:
 * the record keeps the head of the queue and every five-minute run spends itself on it
 * again. The MeetupEvent queue is ordered by `start` and gated on `start > now()`, so
 * the records behind it do not merely wait, they expire unpublished. After
 * NostrPublishFailures::MAX_ATTEMPTS rejections of the same payload the record is
 * stepped over and the batch continues.
 */

const SELF_HEALING_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

/**
 * The `d` tag of a built event — `meetup-event-<id>` or `meetup-<id>`.
 */
function publishedDTag(Event $event): string
{
    foreach ($event->getTags() as $tag) {
        if (($tag[0] ?? null) === 'd') {
            return (string) $tag[1];
        }
    }

    return '';
}

function selfHealingMeetup(): Meetup
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => 'de'])->id,
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ]);

    return Meetup::factory()->create([
        'city_id' => $city->id,
        'nostr_publishing_enabled' => true,
    ]);
}

/**
 * Accept everything except the events of the named records, and keep every event it was
 * handed so a test can look at what actually went out.
 *
 * BOTH PARAMETERS ARE BY REFERENCE, and `$rejectedDTags` has to be: an Artisan command
 * is constructed once per application instance, so the transmitter it was injected with
 * survives every `$this->artisan()` call of a test. A second `mock()` would bind an
 * instance nobody asks for again — the way to change the relays' mind mid-test is to
 * change the array this closure reads.
 *
 * @param  list<string>  $rejectedDTags
 * @param  list<Event>  $sent
 */
function transmitterRejecting(array &$rejectedDTags, array &$sent): void
{
    test()->mock(NostrEventTransmitter::class, function ($mock) use (&$rejectedDTags, &$sent) {
        $mock->shouldReceive('transmit')->andReturnUsing(function (Event $event, array $relayUrls) use (&$rejectedDTags, &$sent): bool {
            $sent[] = $event;

            return ! in_array(publishedDTag($event), $rejectedDTags, true);
        });
    });
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => SELF_HEALING_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

it('steps over a record rejected three times and publishes the one behind it in the same run', function () {
    Log::spy();

    $meetup = selfHealingMeetup();

    $blocked = MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addDay()]);
    $behind = MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addWeek()]);

    $sent = [];
    $rejected = ["meetup-event-{$blocked->id}"];
    transmitterRejecting($rejected, $sent);

    // Three runs, three rejections of the same payload — and each one ends its run at
    // the same record, which is exactly the block being measured: `$behind` is still
    // unpublished after all three.
    foreach ([1, 2, 3] as $expectedAttempts) {
        $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
            ->assertExitCode(1);

        expect((int) $blocked->fresh()->getAttribute(NostrPublishFailures::ATTEMPTS_COLUMN))->toBe($expectedAttempts)
            ->and($behind->fresh()->nostr_coordinate)->toBeNull();
    }

    // The fourth run steps over it.
    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->assertExitCode(0);

    expect($behind->fresh()->nostr_coordinate)->not->toBeNull()
        ->and($blocked->fresh()->nostr_coordinate)->toBeNull()
        ->and((int) $blocked->fresh()->getAttribute(NostrPublishFailures::ATTEMPTS_COLUMN))->toBe(3);

    // And it did not quietly stop trying the relays altogether: the skipped record was
    // not handed to the transmitter a fourth time, the record behind it was.
    $dTagsSent = array_map(publishedDTag(...), $sent);

    expect(array_count_values($dTagsSent)["meetup-event-{$blocked->id}"])->toBe(3)
        ->and($dTagsSent)->toContain("meetup-event-{$behind->id}");
});

it('logs a warning naming the record it steps over', function () {
    Log::spy();

    $meetup = selfHealingMeetup();
    $blocked = MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addDay()]);

    $sent = [];
    $rejected = ["meetup-event-{$blocked->id}"];
    transmitterRejecting($rejected, $sent);

    foreach ([1, 2, 3] as $ignored) {
        $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
            ->assertExitCode(1);
    }

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->expectsOutputToContain("Skipping MeetupEvent #{$blocked->id}")
        ->assertExitCode(0);

    Log::shouldHaveReceived('warning')->once()->with('Nostr calendar publish skipped after repeated failures', [
        'model' => 'MeetupEvent',
        'id' => $blocked->id,
        'attempts' => 3,
    ]);
});

it('tries a skipped record again as soon as its payload changes', function () {
    $meetup = selfHealingMeetup();
    $blocked = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
        'description' => 'Ein Text, den die Relays nicht mochten.',
    ]);

    $sent = [];
    $rejected = ["meetup-event-{$blocked->id}"];
    transmitterRejecting($rejected, $sent);

    foreach ([1, 2, 3] as $ignored) {
        $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
            ->assertExitCode(1);
    }

    // Nothing is retried while the payload is the one that was refused.
    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->assertExitCode(0);

    expect($blocked->fresh()->nostr_coordinate)->toBeNull()
        ->and($sent)->toHaveCount(3);

    // The organiser edits the description. The payload is a different one, so the count
    // does not apply to it — this is what keeps the skip from being a silent deletion.
    // The relays are told to accept it, the way they would once the text is publishable.
    $blocked->update(['description' => 'Ein anderer Text.']);
    $rejected = [];

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->assertExitCode(0);

    expect($blocked->fresh()->nostr_coordinate)->not->toBeNull()
        ->and((int) $blocked->fresh()->getAttribute(NostrPublishFailures::ATTEMPTS_COLUMN))->toBe(0)
        ->and($blocked->fresh()->getAttribute(NostrPublishFailures::HASH_COLUMN))->toBeNull();
});

it('publishes a legacy description holding U+2028 with an id the relays accept', function () {
    $meetup = selfHealingMeetup();
    $meetupEvent = MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addDay()]);

    // A row as it lies in the database today: written before TextNormalizer cleaned
    // these characters, so the repair on the save path cannot have reached it.
    DB::table('meetup_events')
        ->where('id', $meetupEvent->id)
        ->update(['description' => "Wir treffen uns\u{2028}jeden Dienstag."]);

    $sent = [];
    $rejected = [];
    transmitterRejecting($rejected, $sent);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->assertExitCode(0);

    $published = $sent[0];

    $canonicalId = hash('sha256', json_encode(
        [0, $published->getPublicKey(), $published->getCreatedAt(), $published->getKind(), $published->getTags(), $published->getContent()],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR,
    ));

    expect($published->getContent())->toBe("Wir treffen uns\njeden Dienstag.")
        ->and($published->getId())->toBe($canonicalId)
        ->and($meetupEvent->fresh()->nostr_coordinate)->not->toBeNull();
});
