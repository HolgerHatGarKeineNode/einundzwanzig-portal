<?php

namespace App\Support;

use swentel\nostr\Event\Event;
use swentel\nostr\Nip19\Nip19Helper;

/**
 * Turns a stored `nostr_coordinate` into something a reader can actually open.
 *
 * `<kind>:<pubkey>:<d-tag>` (NIP-01) is the canonical address of a parameterized
 * replaceable event, and it is what {@see PublishCalendarEvents} writes to the
 * database. It is also useless to a human: no Nostr client takes it in a URL. The
 * shareable form is NIP-19 `naddr1…`, a bech32 TLV envelope carrying the same three
 * fields plus optional relay hints. This class is the bridge between the two.
 *
 * ## Relay hints are the point, not decoration
 *
 * Issue #49 is a reporter who searched five relays and found nothing. A bare
 * coordinate leaves the next reader with the same problem: correct address, no idea
 * where to look. The TLV carries the relays this portal publishes to (NIP-19,
 * type 1), so a viewer that honours hints queries the right relays without being
 * told. That is why {@see naddr()} folds in the configured relay list rather than
 * emitting the shorter hint-free form.
 *
 * ## Web viewers are chosen by measurement, not by reputation
 *
 * The viewer list below is per-kind because the viewers are. Measured 2026-09-04
 * against a real kind 31923 and a real kind 31924 fetched from live relays:
 *
 *  - `letsmiti.app` routes by kind and the routes are NOT interchangeable. A 31924
 *    on `/event/<naddr>` renders "Event Not Found"; it needs `/calendar/<naddr>`.
 *    A 31923 on `/calendar/` renders "Calendar Not Found". Guessing one route for
 *    both would have shipped a dead link on every meetup page.
 *  - `plektos.app` renders a 31923 fully, but forces a 31924 into its single-event
 *    template. Measured on the "Crypto Talk Series à Neuchâtel" calendar, which
 *    carries 22 `a` tags: plektos produced 470 characters of page text and listed
 *    NONE of the member events, under the headings "About this party" and
 *    "When & where — No time specified", plus an RSVP guest thread. A NIP-52 calendar
 *    IS its collection of `a`-tagged events; a viewer that shows none of them has not
 *    rendered the object, it has rendered a party with no date. Offered for events,
 *    deliberately withheld for calendars — a page that misrepresents the object is
 *    worse than an absent link, because the reader believes what it shows.
 *
 *    CORRECTION, 2026-09-04, and worth keeping as a warning about the sampling: this
 *    comment previously also claimed plektos DROPPED the calendar's description. That
 *    was false, and the error was mine. The calendar I first sampled
 *    (`indy-bitcoin-meetup-calendar-xrwan`) has `content` of length 0 — there was
 *    nothing to drop. Re-measured against a calendar with real content, plektos
 *    renders it verbatim. 28 of 50 kind 31924 events pulled from nos.lol have
 *    non-empty content, so a randomly chosen sample is empty about 44% of the time and
 *    an empty description is indistinguishable from a dropped one. The neighbouring
 *    claim that `mynostr.app` "renders the description" was wrong the same way: the
 *    text it showed was the author's kind 0 profile `about`, not the event content.
 *    A viewer claim needs a sample whose field is provably non-empty first.
 *  - `mynostr.app` server-renders both kinds: the title of a real 31923 and of a real
 *    31924 was present in the raw HTML before any script ran. That — not the richer
 *    body text, see the correction above — is what was actually verified.
 *  - `njump.me` is not in the issue's list and is added as the generic fallback: it
 *    resolves any NIP-19 entity server-side, so it is the link least likely to rot
 *    if a boutique viewer disappears.
 */
final class NostrCalendarAddress
{
    public const KIND_CALENDAR = 31924;

    public const KIND_TIME_BASED_EVENT = 31923;

    private ?string $naddr = null;

    /**
     * @param  list<string>  $relays
     */
    private function __construct(
        public readonly int $kind,
        public readonly string $pubkeyHex,
        public readonly string $dTag,
        public readonly string $coordinate,
        private readonly array $relays,
    ) {}

    /**
     * Parses a stored coordinate, or returns null if there is nothing usable.
     *
     * Returning null rather than throwing is the whole contract: every caller is a
     * view asking "is there an address for this record", and "no" is the normal
     * answer for anything not yet published. A d-tag may itself contain colons, so
     * the split is limited to three parts.
     *
     * @param  list<string>|null  $relays
     */
    public static function fromCoordinate(?string $coordinate, ?array $relays = null): ?self
    {
        $coordinate = trim((string) $coordinate);

        if ($coordinate === '') {
            return null;
        }

        $parts = explode(':', $coordinate, 3);

        if (count($parts) !== 3) {
            return null;
        }

        [$kind, $pubkeyHex, $dTag] = $parts;

        // A pubkey that is not 32 lowercase hex bytes cannot be encoded into a TLV, and
        // a viewer link built from it would 404. Reject here, so the view falls back to
        // the honest "not published" state instead of rendering a broken link.
        if (! preg_match('/^[0-9a-f]{64}$/', $pubkeyHex) || $dTag === '' || ! ctype_digit($kind)) {
            return null;
        }

        return new self(
            (int) $kind,
            $pubkeyHex,
            $dTag,
            $coordinate,
            $relays ?? array_values((array) config('services.nostr.relays', [])),
        );
    }

    /**
     * The NIP-19 `naddr1…` form, with the configured relays as hints.
     *
     * The library reads the kind off the Event object rather than off its own `$kind`
     * argument (see Nip19Helper::convertAddressableEventToBytes), so the kind is set on
     * the event and passed, and the two must not diverge. Verified byte-for-byte
     * against `nak encode naddr` for both kinds, with and without relay hints.
     */
    public function naddr(): string
    {
        if ($this->naddr !== null) {
            return $this->naddr;
        }

        $event = new Event;
        $event->setKind($this->kind);

        return $this->naddr = (new Nip19Helper)->encodeAddr(
            $event,
            $this->dTag,
            $this->kind,
            $this->pubkeyHex,
            $this->relays,
        );
    }

    /**
     * The relays this address was published to — the answer to "where do I look".
     *
     * @return list<string>
     */
    public function relays(): array
    {
        return $this->relays;
    }

    public function isCalendar(): bool
    {
        return $this->kind === self::KIND_CALENDAR;
    }

    /**
     * Web viewers that were measured to render THIS kind. See the class docblock for
     * what each one actually did.
     *
     * @return list<array{label: string, url: string}>
     */
    public function viewers(): array
    {
        $naddr = $this->naddr();

        if ($naddr === '') {
            return [];
        }

        $viewers = [
            ['label' => 'mynostr.app', 'url' => "https://mynostr.app/{$naddr}"],
        ];

        if ($this->isCalendar()) {
            $viewers[] = ['label' => 'letsmiti.app', 'url' => "https://letsmiti.app/calendar/{$naddr}"];
        } else {
            $viewers[] = ['label' => 'plektos.app', 'url' => "https://plektos.app/{$naddr}"];
            $viewers[] = ['label' => 'letsmiti.app', 'url' => "https://letsmiti.app/event/{$naddr}"];
        }

        $viewers[] = ['label' => 'njump.me', 'url' => "https://njump.me/{$naddr}"];

        return $viewers;
    }
}
