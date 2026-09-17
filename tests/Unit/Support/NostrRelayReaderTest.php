<?php

use App\Support\NostrRelayReader;
use swentel\nostr\RelayResponse\RelayResponse;

/*
|--------------------------------------------------------------------------
| When is a relay answer complete? (plan risk R11)
|--------------------------------------------------------------------------
|
| The frames are built with swentel's own RelayResponse::create() from the JSON a
| relay sends, so the verdict is tested against the exact objects Request::send()
| returns — not against a hand-made imitation of them.
*/

const READER_OWN_SUB = 'ownSubscription';

/**
 * @param  list<string>  $jsonFrames
 * @return list<mixed>
 */
function relayFrames(array $jsonFrames): array
{
    return array_map(fn (string $json) => RelayResponse::create(json_decode($json)), $jsonFrames);
}

function readerEventJson(string $subscriptionId, string $id): string
{
    return json_encode(['EVENT', $subscriptionId, [
        'id' => $id, 'pubkey' => str_repeat('a', 64), 'created_at' => 1789600000, 'kind' => 31925,
        'tags' => [['d', 'x']], 'content' => '', 'sig' => str_repeat('b', 128),
    ]]);
}

it('is complete with the events of its own subscription once its EOSE arrived', function () {
    $result = NostrRelayReader::interpret(READER_OWN_SUB, relayFrames([
        readerEventJson(READER_OWN_SUB, str_repeat('1', 64)),
        readerEventJson('someoneElse', str_repeat('2', 64)),
        '["EOSE","'.READER_OWN_SUB.'"]',
    ]));

    expect($result->complete)->toBeTrue()
        ->and(array_column($result->events, 'id'))->toBe([str_repeat('1', 64)])
        ->and($result->events[0]['tags'])->toBe([['d', 'x']]);
});

it('is complete and empty when the relay has nothing, which is a real zero', function () {
    expect(NostrRelayReader::interpret(READER_OWN_SUB, relayFrames(['["EOSE","'.READER_OWN_SUB.'"]'])))
        ->complete->toBeTrue()
        ->events->toBe([]);
});

it('is unknown when the only EOSE belongs to another subscription', function () {
    expect(NostrRelayReader::interpret(READER_OWN_SUB, relayFrames([
        readerEventJson(READER_OWN_SUB, str_repeat('1', 64)),
        '["EOSE","someoneElse"]',
    ])))
        ->complete->toBeFalse()
        ->events->toBe([]);
});

it('is unknown for the frames swentel returned from a zooid that demands AUTH', function () {
    // Measured 2026-09-17 against a local zooid holding two RSVPs: swentel authenticates
    // with its constant key, keeps ONE further frame and stops. One of two events, no EOSE.
    $result = NostrRelayReader::interpret(READER_OWN_SUB, relayFrames([
        '["AUTH","c8637534eb5bc199"]',
        '["AUTH","c8637534eb5bc199"]',
        '["CLOSED","'.READER_OWN_SUB.'","auth-required: authentication is required for access"]',
        '["OK","'.str_repeat('9', 64).'",true,""]',
        readerEventJson(READER_OWN_SUB, str_repeat('1', 64)),
    ]));

    expect($result->complete)->toBeFalse()
        ->and($result->events)->toBe([])
        ->and($result->failure)->toContain('auth-required');
});

it('counts an EOSE that follows a CLOSED for a re-sent REQ, and voids one that precedes it', function () {
    expect(NostrRelayReader::interpret(READER_OWN_SUB, relayFrames([
        '["CLOSED","'.READER_OWN_SUB.'","auth-required: authentication is required for access"]',
        '["EOSE","'.READER_OWN_SUB.'"]',
    ])))->complete->toBeTrue()
        ->and(NostrRelayReader::interpret(READER_OWN_SUB, relayFrames([
            '["EOSE","'.READER_OWN_SUB.'"]',
            '["CLOSED","'.READER_OWN_SUB.'","ERROR: bad req: arr too big"]',
        ])))->complete->toBeFalse();
});

it('is unknown for the transport error swentel records when the socket fails', function () {
    // The exact shape of Request::send()'s catch block, e.g. relay.damus.io on 2026-09-17.
    $result = NostrRelayReader::interpret(READER_OWN_SUB, [['ERROR', '', false, 'Invalid status code 503.']]);

    expect($result->complete)->toBeFalse()
        ->and($result->failure)->toBe('transport: Invalid status code 503.');
});

it('is unknown when a transport error sits next to an EOSE', function () {
    // swentel does not produce this pair today (its catch block starts a list holding the error alone);
    // the rule is that a transport error voids the answer wherever it appears.
    $frames = relayFrames(['["EOSE","'.READER_OWN_SUB.'"]']);
    $frames[] = ['ERROR', '', false, 'Connection reset by peer'];

    expect(NostrRelayReader::interpret(READER_OWN_SUB, $frames)->complete)->toBeFalse();
});

it('is unknown when the relay went silent after some events', function () {
    expect(NostrRelayReader::interpret(READER_OWN_SUB, relayFrames([
        readerEventJson(READER_OWN_SUB, str_repeat('1', 64)),
        '["NOTICE","ERROR: too many concurrent REQs"]',
    ])))
        ->complete->toBeFalse()
        ->events->toBe([]);
});
