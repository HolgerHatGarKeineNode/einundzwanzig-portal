<?php

use App\Support\NostrEventVerifier;
use Tests\Fixtures\NostrTestEvents;

/*
|--------------------------------------------------------------------------
| NIP-01 serialisation: the bytes that decide an id
|--------------------------------------------------------------------------
|
| NIP-01 names seven characters that are escaped and says every other one goes in
| verbatim: 0x0A as \n, 0x22 as \", 0x5C as \\, 0x0D as \r, 0x09 as \t, 0x08 as \b,
| 0x0C as \f. Two of them are measured here rather than assumed, because they are the
| ones where implementations were found to disagree.
*/

it('accepts an event whose content carries a backspace and a form feed', function () {
    $event = NostrTestEvents::signed(NostrTestEvents::ATTENDEE_SECRET, 1, [], 1789600000, "a\x08b\x0cc");

    expect(bin2hex($event['content']))->toBe('6108620c63')
        ->and(NostrEventVerifier::verify($event))->toBeTrue();
});

it('computes the id over the short escapes the spec names, not over six-character ones', function () {
    $event = NostrTestEvents::signed(NostrTestEvents::ATTENDEE_SECRET, 1, [], 1789600000, "a\x08b\x0cc");

    $shortEscapes = hash('sha256', '[0,"'.$event['pubkey'].'",1789600000,1,[],"a\b'.'b\f'.'c"]');
    $sixCharacterEscapes = hash('sha256', '[0,"'.$event['pubkey'].'",1789600000,1,[],"abc"]');

    expect($event['id'])->toBe($shortEscapes)
        ->and($event['id'])->not->toBe($sixCharacterEscapes);
});

it('rejects the same content signed by a library that hashes those two bytes differently', function () {
    // Measured 2026-09-18 with the `nak` build in this environment (fiatjaf.com/nostr):
    // for content "a<0x08>b<0x0C>c" it produced the id below, which is the
    // six-character-escape hash — not the one NIP-01 describes. The reviewer measured
    // nbd-wtf/go-nostr agreeing with PHP on the same input.
    //
    // So this is a real interop gap, and the direction is the safe one: an event whose
    // id does not match its content under the spec's own rule is not counted. It costs
    // nothing in practice — an RSVP carrying a backspace in its content does not exist —
    // and the alternative (accepting two different ids for one content) would mean
    // accepting an id the signature does not bind.
    $foreign = NostrTestEvents::signed(NostrTestEvents::ATTENDEE_SECRET, 1, [], 1789600000, "a\x08b\x0cc");
    $foreign['id'] = 'fc9dff1d45e879ac8cecbce1e256809a9473f9da76d9a65a47cdd69bd14cd015';
    $foreign['sig'] = '271b078ca0e76c3559d06b4c7a59238febed90d4929ef7128efb6ed15d5e11fe09b51d7dadc70675088c06bf446d61abddf13174950c87a5dbd10c2f33d2e8d5';

    expect(NostrEventVerifier::verify($foreign))->toBeFalse();
});

it('accepts the line and paragraph separators verbatim, which the library check does not', function () {
    expect(NostrEventVerifier::verify(NostrTestEvents::u2028Rsvp()))->toBeTrue();
});

it('rejects an event whose id does not match its content', function () {
    $event = NostrTestEvents::signed(NostrTestEvents::ATTENDEE_SECRET, 1, [], 1789600000, 'hello');
    $tampered = [...$event, 'content' => 'hellp'];

    expect(NostrEventVerifier::verify($tampered))->toBeFalse();
});

it('rejects malformed shapes without touching the signature check', function (array $overrides) {
    $event = NostrTestEvents::signed(NostrTestEvents::ATTENDEE_SECRET, 1, [['d', 'x']], 1789600000, 'hi');

    expect(NostrEventVerifier::verify([...$event, ...$overrides]))->toBeFalse();
})->with([
    'id not hex' => [['id' => str_repeat('z', 64)]],
    'id too short' => [['id' => str_repeat('a', 63)]],
    'pubkey uppercase' => [['pubkey' => strtoupper('3c72addb4fdf09af94f0c94d7fe92a386a7e70cf8a1d85916386bb2535c7b1b1')]],
    'sig too short' => [['sig' => str_repeat('a', 127)]],
    'created_at as string' => [['created_at' => '1789600000']],
    'kind as string' => [['kind' => '1']],
    'content not a string' => [['content' => 42]],
    'tags not a list' => [['tags' => ['d' => 'x']]],
    'tag value not a string' => [['tags' => [['d', 1]]]],
    'tag not an array' => [['tags' => ['d']]],
]);
