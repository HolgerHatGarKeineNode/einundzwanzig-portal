<?php

namespace Tests\Fixtures;

use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;

/**
 * Really signed Nostr events for the RSVP ingest tests.
 *
 * Signed through swentel/nostr-php, whose id matches NIP-01 for every content that holds
 * no U+2028/U+2029 — none of the events built here do. The one event that does is a
 * fixture signed by `nak` instead ({@see self::u2028Rsvp()}), because swentel would hash it
 * wrongly and the test would then prove nothing.
 *
 * The secrets are throwaway test values, not keys anybody uses.
 */
final class NostrTestEvents
{
    public const PUBLISHER_SECRET = '2222222222222222222222222222222222222222222222222222222222222222';

    public const ATTENDEE_SECRET = '3333333333333333333333333333333333333333333333333333333333333333';

    public const OTHER_ATTENDEE_SECRET = '4444444444444444444444444444444444444444444444444444444444444444';

    public const FOREIGN_PUBLISHER_SECRET = '5555555555555555555555555555555555555555555555555555555555555555';

    /**
     * A kind 31925 by ATTENDEE_SECRET for `31923:<publisher>:meetup-event-1`, content
     * "Wir kommen<U+2028>zu zweit", created_at 1789600000. Signed with `nak event` on
     * 2026-09-17 and accepted by a khatru relay (`nak serve`), i.e. its id is canonical;
     * swentel's Event::verify() returns false for it.
     *
     * Built in code with mb_chr() rather than kept as a raw character: the separator is
     * invisible in a source file, and pint's `line_ending` fixer rewrites it into a real
     * newline wherever it sits in a comment (measured 2026-09-17; inside a string literal
     * it survived). One moved line would silently turn this fixture into a plain event.
     *
     * @return array<string, mixed>
     */
    public static function u2028Rsvp(): array
    {
        return [
            'kind' => 31925,
            'id' => 'a4d7cfab9342955920d8a3fc4b0da1f6bf04e9919300429031a5eaa954b0a813',
            'pubkey' => '3c72addb4fdf09af94f0c94d7fe92a386a7e70cf8a1d85916386bb2535c7b1b1',
            'created_at' => 1789600000,
            'tags' => [
                ['a', '31923:466d7fcae563e5cb09a0d1870bb580344804617879a14949cf22285f1bae3f27:meetup-event-1'],
                ['status', 'accepted'],
                ['d', 'rsvp-u2028'],
            ],
            'content' => 'Wir kommen'.mb_chr(0x2028).'zu zweit',
            'sig' => '4b6952666b1785520d90d6fe3a0adb6f4a515ae3e88adf625354878d26fb797a8677e689a5d7af24809df0ded9898c214d6991674abf90a31cd82a5a895b1e54',
        ];
    }

    public static function pubkey(string $secret): string
    {
        return (new Key)->getPublicKey($secret);
    }

    public static function coordinate(int $meetupEventId, string $publisherSecret = self::PUBLISHER_SECRET): string
    {
        return '31923:'.self::pubkey($publisherSecret).':meetup-event-'.$meetupEventId;
    }

    /**
     * @param  list<list<string>>  $tags
     * @return array<string, mixed>
     */
    public static function signed(string $secret, int $kind, array $tags, int $createdAt, string $content = ''): array
    {
        $event = new Event;
        $event->setKind($kind);
        $event->setTags($tags);
        $event->setContent($content);
        $event->setCreatedAt($createdAt);

        (new Sign)->signEvent($event, $secret);

        return json_decode($event->toJson(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<list<string>>  $extraTags
     * @return array<string, mixed>
     */
    public static function rsvp(
        int $meetupEventId,
        string $status,
        int $createdAt,
        string $secret = self::ATTENDEE_SECRET,
        string $dTag = 'rsvp-1',
        array $extraTags = [],
        ?string $coordinate = null,
    ): array {
        return self::signed($secret, 31925, [
            ['a', $coordinate ?? self::coordinate($meetupEventId)],
            ['d', $dTag],
            ['status', $status],
            ...$extraTags,
        ], $createdAt);
    }

    /**
     * @param  list<list<string>>  $tags
     * @return array<string, mixed>
     */
    public static function deletion(array $tags, int $createdAt, string $secret = self::ATTENDEE_SECRET): array
    {
        return self::signed($secret, 5, [...$tags, ['k', '31925']], $createdAt);
    }
}
