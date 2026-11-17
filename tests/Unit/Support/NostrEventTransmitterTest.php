<?php

use App\Support\NostrEventTransmitter;
use App\Support\NostrRelayAnswer;
use swentel\nostr\Event\Event;
use swentel\nostr\RelayResponse\RelayResponse;

/**
 * What counts as "the relay accepted the event" — tested against the frame objects
 * swentel/nostr-php really builds, without a socket.
 */
const TRANSMITTER_EVENT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

it('accepts only an OK true that names the event that was sent', function () {
    $answer = NostrEventTransmitter::interpret('wss://relay.test', TRANSMITTER_EVENT_ID, [
        RelayResponse::create(['OK', TRANSMITTER_EVENT_ID, true, '']),
    ]);

    expect($answer->accepted)->toBeTrue();
});

it('does not take a frame that is not an OK true for this event as an acceptance', function (array $frames, string $message) {
    $answer = NostrEventTransmitter::interpret('wss://relay.test', TRANSMITTER_EVENT_ID, $frames);

    expect($answer->accepted)->toBeFalse()
        ->and($answer->message)->toContain($message);
})->with([
    // The defect of 2026-11-17: swentel's `isSuccess` is true for each of these frames,
    // and the old transmitter took `isSuccess` as "accepted".
    'a NOTICE' => [[RelayResponse::create(['NOTICE', 'rate-limited: slow down'])], 'rate-limited: slow down'],
    'an AUTH challenge' => [[RelayResponse::create(['AUTH', 'challenge-string'])], 'auth'],
    'an OK true for another event' => [[RelayResponse::create(['OK', str_repeat('b', 64), true, ''])], 'another event id'],
    // What swentel leaves behind for an `OK false` or a broken socket.
    'a refusal' => [[['ERROR', '', false, 'blocked: pubkey not admitted']], 'blocked: pubkey not admitted'],
    'no frame at all' => [[], 'no OK frame'],
]);

it('pins the library behaviour the rule guards against: swentel calls a NOTICE a success', function () {
    expect(RelayResponse::create(['NOTICE', 'rate-limited: slow down'])->isSuccess)->toBeTrue();
});

it('asks every relay and lets a relay sit out after two refusals in a row', function () {
    $transmitter = new class extends NostrEventTransmitter
    {
        /** @var list<string> */
        public array $offered = [];

        protected function offer(Event $event, string $relayUrl): NostrRelayAnswer
        {
            $this->offered[] = $relayUrl;

            return $relayUrl === 'wss://closed.relay.test'
                ? NostrRelayAnswer::refused($relayUrl, 'blocked: not on the whitelist')
                : NostrRelayAnswer::accepted($relayUrl);
        }
    };

    $event = (new Event)->setId(TRANSMITTER_EVENT_ID);
    $relays = ['wss://closed.relay.test', 'wss://open.relay.test/'];

    $first = $transmitter->transmit($event, $relays);
    $transmitter->transmit($event, $relays);
    $third = $transmitter->transmit($event, $relays);

    expect($first->acceptedBy())->toBe(['wss://open.relay.test'])
        ->and($first->reached(1))->toBeTrue()
        ->and($first->reached(2))->toBeFalse()
        ->and($first->summary())->toContain('blocked: not on the whitelist')
        // Offered twice, then left alone; the open relay is asked every time.
        ->and(array_count_values($transmitter->offered))->toBe(['wss://closed.relay.test' => 2, 'wss://open.relay.test' => 3])
        ->and($third->answerOf('wss://closed.relay.test')->offered)->toBeFalse();
});
