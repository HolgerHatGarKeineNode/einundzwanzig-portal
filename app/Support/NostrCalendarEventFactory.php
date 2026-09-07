<?php

namespace App\Support;

use App\Models\City;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Str;
use swentel\nostr\Event\Event;

/**
 * Baut unsignierte NIP-52-Kalender-Events aus Meetups und MeetupEvents.
 *
 * Kein Netzwerk und kein Schluessel: keine Signatur, keine Relay-Uebertragung, kein
 * Schreiben in die Datenbank. Das macht die Tag-Logik ohne beides testbar; Signieren
 * und Versenden uebernehmen die Commands (`nostr:publish-calendar`,
 * `nostr:republish-calendar`) mit {@see NostrEventTransmitter}.
 *
 * NOT free of database READS, and one of them is load-bearing rather than incidental:
 * `forMeetup()` queries the meetup's published events to build its `a` tags (see
 * below). The city/country relations these methods walk were always lazy loads too;
 * the earlier "rein und ohne I/O" here was about writes and sockets, and is stated
 * that way now so nobody removes the query believing it to be an accident.
 *
 * Kinds nach NIP-52 (github.com/nostr-protocol/nips/blob/master/52.md):
 *  - 31924 Calendar: Pflicht-Tags sind `d` und `title`, dazu ein wiederholtes `a` je
 *    enthaltenem Kalender-Event — das ist es, was einen Kalender zur Sammlung macht.
 *    Es fehlte bis Issue #104, weshalb jeder publizierte Kalender leer war.
 *    `location`/`g`/`r` sind hier eine bewusste Erweiterung nach Issue #34 (kein
 *    Verstoss gegen die Spec — unbekannte Tags werden von Clients ignoriert).
 *  - 31923 Time-Based Calendar Event: `d`, `title`, `start` Pflicht; `D` (Tages-
 *    Granularitaet, floor(start / 86400)) ebenfalls Pflicht. `start_tzid` ist
 *    OPTIONAL und wird weggelassen, wenn die Zone nicht bestimmbar ist —
 *    {@see LocationTimezone}.
 *
 * The `t` topic tags added for issue #69 sit on both kinds, but they are spec'd on only
 * one of them: NIP-52 lists `t` ("hashtag to categorize calendar event") among the
 * optional tags of 31923, while its 31924 section lists `a` and nothing else. On the
 * calendar they are therefore the same kind of deliberate extension as `location`/`g`/`r`
 * above — a client that does not know them ignores them, and a client filtering
 * `{"#t": ["berlin"]}` finds the calendar as well as its events. See
 * {@see self::topicTags()} for the derivation and the normalisation rule.
 */
class NostrCalendarEventFactory
{
    private const KIND_CALENDAR = 31924;

    private const KIND_TIME_BASED_EVENT = 31923;

    /**
     * The `t` topics every published calendar event carries, wherever it is.
     *
     * They stand in front of the geography so that a client filtering `{"#t":
     * ["bitcoin"]}` sees this portal's events at all — the geography alone is only
     * findable by someone who already knows the region's name.
     */
    private const STATIC_TOPICS = ['bitcoin', 'meetup'];

    /**
     * The word a cancelled event is marked with, in the two spellings each of its two
     * audiences uses (issue #141).
     *
     * ## Why a marker at all, and why in the title
     *
     * NIP-52 HAS NO CANCELLATION STATUS for kinds 31922/31923. Researched for #141
     * against the spec as it stood on 2026-02-13 and against all 457 open pull requests
     * on nostr-protocol/nips; no client of the ten examined writes or reads one. The
     * `status` tag NIP-52 does define belongs to kind 31925, the RSVP, and its
     * vocabulary is `accepted`/`declined`/`tentative` — an attendee's answer, not the
     * organiser's. Verified again against nostr-protocol/nips at
     * c3fd9af17939316bf6d0d83a5759100f8b0a1bdb (2026-09-04), 52.md lines 186-201.
     *
     * That leaves two ways to tell the relays. DELETE the record with a NIP-09 kind 5,
     * or KEEP it and mark it. The owner chose to keep and mark, and the reason is the
     * one {@see \App\Http\Controllers\DownloadMeetupCalendar} already gives for the ICS
     * feed: a subscriber has already materialised the entry, so an entry that merely
     * stops being delivered tells them nothing. A deleted kind 31923 is the disappearing
     * entry — and worse, NIP-09 deletion is a request relays may ignore, so the likely
     * outcome is a record that is gone from some relays and unchanged on the rest.
     *
     * THE TITLE MARKER IS THE LOAD-BEARING HALF, not the tags below it. It is the only
     * thing all ten examined renderers display, because it needs no support for a
     * convention none of them implements. The same reflex shows in the leading NIP-52
     * extension proposal: nips PR #2351 (NIP-52R), asked what a client should do to call
     * off ONE occurrence of a recurring series, answers "publish a new non-recurring
     * event at the specific time, note in its description that it replaces the
     * occurrence". Read for #141 as "NIP-52R recommends exactly this"; it does not —
     * its scope is a recurring series and its vehicle is a NEW event, not a marker on
     * the existing one. What it is, is a draft NIP reaching for prose in the same place,
     * for want of a tag. The tags below are the machine-readable form for a client that
     * one day does look, and today they are also what makes the payload — and therefore
     * the #92 fingerprint — differ in a way no title normalisation can flatten.
     *
     * ## Two spellings, each in its own vocabulary
     *
     * `CANCELLED`, double-l, in the human-facing marker: that is the word this portal
     * already publishes for the same event in its ICS feed (`STATUS:CANCELLED`,
     * RFC 5545 §3.8.1.11), and inventing a second spelling for the same fact in a second
     * feed would be a difference with no meaning behind it.
     *
     * `canceled`, single-l, in the tag value: that is the ONLY spelling in the NIP set.
     * Measured over all 100 NIP files at the commit above — two occurrences of
     * `canceled` (69.md line 59, an `s` tag's value vocabulary; 90.md line 204, prose)
     * and zero of `cancelled`. A tag value is matched byte for byte by relays and
     * clients, so it follows the vocabulary it will be compared against, not the one
     * the sentence next to it uses.
     *
     * NEITHER IS TRANSLATED, deliberately, and for the reason {@see self::topicTags()}
     * gives at length: these events are replaceable and get re-published, so a value
     * that moved with the locale of whichever process happened to run the publisher
     * would make the same event say different things over time.
     */
    public const CANCELLED_TITLE_PREFIX = 'CANCELLED: ';

    /**
     * The line prepended to a cancelled event's content.
     *
     * The title carries the marker for every renderer that shows a title; this carries
     * it for the generic clients that render an unknown kind by printing its `content`
     * and nothing else. Both come off again on an un-cancel, because both are DERIVED
     * from `cancelled_at` on every build rather than written into the record — see the
     * un-cancel test in `tests/Feature/Console/NostrCalendarCancellationTest.php`.
     */
    public const CANCELLED_CONTENT_NOTICE = 'CANCELLED: this event is not taking place.';

    /**
     * The tag value, and the NIP-32 label that mirrors it. See
     * {@see self::CANCELLED_TITLE_PREFIX} for the spelling and {@see self::cancellationTags()}
     * for why the same fact is written twice.
     */
    public const CANCELLED_STATUS = 'canceled';

    /**
     * The NIP-32 label namespace the mirror is published under.
     */
    public const CANCELLED_LABEL_NAMESPACE = 'status';

    /**
     * A blank event of the given kind, stamped from the APPLICATION clock.
     *
     * swentel\nostr\Event\Event::__construct() already stamps `created_at` with PHP's
     * `time()`. It is overwritten here for two reasons. First, `created_at` is not
     * decoration on these kinds: NIP-01 replaces a parameterized-replaceable event only
     * when the newcomer is NEWER, so this field is the mechanism by which
     * `nostr:republish-calendar` repairs anything at all — it belongs in this file
     * where that is visible, not in a vendor constructor. Second, `time()` ignores
     * Carbon's test clock, so without this the "the republish carries a newer
     * created_at" assertion could not be written at all: both events would land in the
     * same second and compare equal.
     *
     * The same-second case is real, not hypothetical, and NIP-01 resolves it by
     * keeping the event with the lowest id — i.e. a republish inside the same second
     * as the original publish can be silently discarded by the relay. In practice the
     * two are minutes to months apart, and the republish command paces itself
     * (`--sleep`, default 2 s), so no record is re-sent twice within one second.
     */
    private static function newEvent(int $kind): Event
    {
        $event = new Event;
        $event->setKind($kind);
        $event->setCreatedAt(now()->getTimestamp());

        return $event;
    }

    public static function calendarDTag(Meetup $meetup): string
    {
        return "meetup-{$meetup->id}";
    }

    public static function eventDTag(MeetupEvent $meetupEvent): string
    {
        return "meetup-event-{$meetupEvent->id}";
    }

    /**
     * Die `<kind>:<pubkey>:<d-tag>`-Koordinate, unter der ein parameterized-
     * replaceable Event auffindbar ist (NIP-01).
     */
    public static function coordinate(int $kind, string $pubkeyHex, string $dTag): string
    {
        return "{$kind}:{$pubkeyHex}:{$dTag}";
    }

    public static function forMeetup(Meetup $meetup): Event
    {
        $event = self::newEvent(self::KIND_CALENDAR);
        $event->setContent((string) ($meetup->intro ?? ''));
        $event->addTag(['d', self::calendarDTag($meetup)]);
        $event->addTag(['title', $meetup->name]);

        $location = self::meetupLocation($meetup);
        if ($location !== null) {
            $event->addTag(['location', $location]);
        }

        // city.latitude/longitude sind Pflichtfelder (nicht nullable) — anders als die
        // duennbesetzten osm_lat/osm_lon, die nur bei verifiziertem Nominatim-Treffer
        // gesetzt sind. Fuer den Kalender-g-Tag ist die City-Koordinate deshalb der
        // verlaessliche Wert, nicht die OSM-Anreicherung.
        $geohash = self::geohashFor($meetup->city?->latitude, $meetup->city?->longitude);
        if ($geohash !== null) {
            $event->addTag(['g', $geohash]);
        }

        foreach (self::topicTags($meetup->city) as $topic) {
            $event->addTag(['t', $topic]);
        }

        foreach (self::socialLinks($meetup) as $url) {
            $event->addTag(['r', $url]);
        }

        /*
         * The `a` tags that make this a calendar rather than a headline (issue #104).
         *
         * NIP-52: "A calendar is a collection of calendar events, represented as a
         * custom addressable list event using kind 31924", whose `a` tag is
         * `["a", "<31922 or 31923>:<calendar event author pubkey>:<d-identifier of
         * calendar event>", "<optional relay url>"]`. Until this change the portal
         * emitted `d`, `title`, `location`, `g`, `t` and `r` and no `a` at all, so
         * every published calendar was empty — the reporter's included. The kind 31923
         * side already pointed AT the calendar; NIP-52 calls that "a request for
         * inclusion", and the request was never granted.
         *
         * NO RELAY HINT in the third position, although the spec allows one. The
         * portal's relay set is configuration that changes independently of the events
         * it has already published, so a hint written today is a claim about a
         * deployment detail tomorrow; a stale hint sends a reader to a relay that never
         * had the event, which is worse than no hint, because with no hint the reader
         * uses the relay it found the calendar on — and that relay has the events too,
         * since both kinds go out over the same list. The `a` tag on the event side
         * omits it for the same reason.
         *
         * ORDER IS `start` THEN `id`, deliberately deterministic. A parameterized-
         * replaceable event gets re-sent (see `nostr:republish-calendar`), and a tag
         * list that reshuffles between runs changes the event id for no reason.
         *
         * SIZE. One tag is roughly 90 bytes, the set grows only by events this portal
         * published, and publishing requires `start > now()`, so a weekly meetup adds
         * about 5 KB per year — far below any relay's event size limit. Worth
         * revisiting past roughly a thousand events on one meetup.
         */
        foreach (self::publishedEventCoordinates($meetup) as $coordinate) {
            $event->addTag(['a', $coordinate]);
        }

        return $event;
    }

    /**
     * The coordinates of this meetup's already-published kind 31923 events.
     *
     * The STORED coordinate is used verbatim rather than recomputed from the current
     * publisher key: the `a` tag has to name where the event actually is on the relays,
     * and NIP-52 spells that position "<calendar event author pubkey>". After a key
     * rotation a recomputed address would point at an event that was never published
     * under it. The `31923:` prefix check is the same "no tag beats a wrong tag" rule
     * the rest of this class follows — a row holding anything else contributes nothing.
     *
     * A CANCELLED EVENT STAYS LISTED (issue #141). Dropping its `a` tag would make the
     * calendar's own reader lose sight of exactly the record that has something to say,
     * which is the disappearing-entry failure #56 rejected for the ICS feed; the
     * cancellation travels in the kind 31923 itself, where a reader who followed the tag
     * reads it. It also keeps this list — and therefore the calendar's fingerprint —
     * unmoved by a cancellation, so #141 costs one re-send of the event and none of the
     * calendar.
     *
     * @return list<string>
     */
    private static function publishedEventCoordinates(Meetup $meetup): array
    {
        $prefix = self::KIND_TIME_BASED_EVENT.':';

        return $meetup->meetupEvents()
            ->whereNotNull('nostr_coordinate')
            ->orderBy('start')
            ->orderBy('id')
            ->pluck('nostr_coordinate')
            ->filter(fn (mixed $coordinate): bool => is_string($coordinate) && str_starts_with($coordinate, $prefix))
            ->values()
            ->all();
    }

    /**
     * The coordinate pair to resolve the time zone from: the event's own Nominatim
     * match when it has a complete one, otherwise the meetup city's.
     *
     * @return array{float|null, float|null}
     */
    private static function positionFor(MeetupEvent $meetupEvent): array
    {
        if ($meetupEvent->osm_lat !== null && $meetupEvent->osm_lon !== null) {
            return [(float) $meetupEvent->osm_lat, (float) $meetupEvent->osm_lon];
        }

        $city = $meetupEvent->meetup->city;

        if ($city?->latitude === null || $city?->longitude === null) {
            return [null, null];
        }

        return [(float) $city->latitude, (float) $city->longitude];
    }

    public static function forMeetupEvent(MeetupEvent $meetupEvent, string $pubkeyHex): Event
    {
        $event = self::newEvent(self::KIND_TIME_BASED_EVENT);
        $event->setContent(self::eventContent($meetupEvent));
        $event->addTag(['d', self::eventDTag($meetupEvent)]);
        $event->addTag(['title', self::eventTitle($meetupEvent)]);

        // Directly behind the title, because the marker in it and these tags are one
        // statement in two forms. The position is otherwise arbitrary — but it is fixed,
        // since NostrPayloadFingerprint hashes the tag list in order.
        foreach (self::cancellationTags($meetupEvent) as $tag) {
            $event->addTag($tag);
        }

        $start = $meetupEvent->start->getTimestamp();
        $event->addTag(['start', (string) $start]);
        $event->addTag(['D', (string) intdiv($start, 86400)]);

        if ($meetupEvent->end) {
            $event->addTag(['end', (string) $meetupEvent->end->getTimestamp()]);
        }

        /*
         * `start_tzid` from the event's LOCATION, not from its country (issue #104).
         *
         * The `start` tag above is already right — it is an absolute Unix timestamp —
         * but a client renders the wall clock from `start_tzid`, so a wrong identifier
         * moves the event to another day. See {@see LocationTimezone} for why a
         * country-keyed map cannot be made right and what replaced it.
         *
         * THE EVENT'S OWN OSM COORDINATE WINS over the meetup city's, and both halves
         * come from the same source or neither: a latitude from the venue paired with a
         * longitude from the city names a point that is in neither place. The venue is
         * preferred because a time zone boundary can run between a city record's centre
         * and the venue — that is not exotic, it is the Indiana case in the issue, where
         * neighbouring counties sit in different zones. Where no Nominatim match exists
         * the city coordinate carries it, and `cities.latitude`/`longitude` are NOT
         * nullable, so a meetup with a city always has a position.
         *
         * A null result means "cannot be determined", and then NO TAG IS EMITTED. The
         * tag is optional in NIP-52, and issue #104 is the demonstration that a
         * plausible default is worse than nothing: Europe/Berlin on an Indianapolis
         * meetup was believed by every client that read it.
         */
        [$timezoneLatitude, $timezoneLongitude] = self::positionFor($meetupEvent);

        $timezone = LocationTimezone::forLocation(
            $meetupEvent->meetup->city?->country?->code,
            $timezoneLatitude,
            $timezoneLongitude,
        );

        if ($timezone !== null) {
            $event->addTag(['start_tzid', $timezone]);
        }

        $location = $meetupEvent->location ?: $meetupEvent->osm_name;
        if ($location) {
            $event->addTag(['location', $location]);
        }

        // Erst die veranstaltungsgenaue OSM-Koordinate, wenn vorhanden (duennbesetzt,
        // nur bei verifiziertem Nominatim-Treffer). Sonst die immer vorhandene
        // City-Koordinate des Meetups — besser als gar kein g-Tag.
        $geohash = self::geohashFor($meetupEvent->osm_lat, $meetupEvent->osm_lon)
            ?? self::geohashFor($meetupEvent->meetup->city?->latitude, $meetupEvent->meetup->city?->longitude);
        if ($geohash !== null) {
            $event->addTag(['g', $geohash]);
        }

        // Deliberately the MEETUP's city, not the event's own OSM match above: the `g`
        // tag wants the most precise point it can get, a topic wants the name people
        // search for. `osm_name` is a venue ("Bar 21"), never an administrative area.
        foreach (self::topicTags($meetupEvent->meetup->city) as $topic) {
            $event->addTag(['t', $topic]);
        }

        /*
         * The event's own links (issue #70), one `r` tag each, in the organiser's order.
         *
         * `r` is the tag NIP-52 names for this: "references / links to web pages,
         * documents, video calls, recorded videos, etc.", listed among the tags common
         * to both calendar event kinds. It is also what forMeetup() above already emits
         * for a meetup's social links, so both kinds speak one vocabulary.
         *
         * THE LABEL IS NOT PUBLISHED, deliberately. NIP-52 gives `r` no label position,
         * and the third element of an `r` tag is not free: NIP-65 puts `read`/`write`
         * there and NIP-34 puts `euc`, i.e. it is a MARKER slot with a per-kind
         * vocabulary. Writing "Meetup.com" into it would hand a client a marker it has
         * to guess at, so a label stays where it is understood — the portal's own page
         * and the API. Reference for both: github.com/nostr-protocol/nips (52.md, 65.md,
         * 34.md), read 2026-09-05.
         */
        foreach ($meetupEvent->linkList() as $link) {
            $event->addTag(['r', $link['url']]);
        }

        $event->addTag(['a', self::coordinate(
            self::KIND_CALENDAR,
            $pubkeyHex,
            self::calendarDTag($meetupEvent->meetup)
        )]);

        return $event;
    }

    /**
     * The event's title, carrying the cancellation marker when it is off (issue #141).
     *
     * DERIVED ON EVERY BUILD from `cancelled_at`, never written into the record. That is
     * what makes the reversal work without a second mechanism: an organiser who takes a
     * cancellation back (#140) produces a payload identical to the one published before
     * the cancellation, so {@see NostrPayloadFingerprint} lands back on the value it had
     * then and `nostr:republish-calendar --changed` re-sends the clean event.
     */
    private static function eventTitle(MeetupEvent $meetupEvent): string
    {
        $title = $meetupEvent->title ?: $meetupEvent->meetup->name;

        return $meetupEvent->isCancelled() ? self::CANCELLED_TITLE_PREFIX.$title : $title;
    }

    /**
     * The event's content, with the cancellation notice in front of it (issue #141).
     *
     * A blank line between notice and description, so a renderer that treats the content
     * as prose does not run the two into one paragraph. An event without a description
     * gets the notice alone rather than a notice followed by two newlines — trailing
     * whitespace is part of the payload, and therefore part of the fingerprint.
     */
    private static function eventContent(MeetupEvent $meetupEvent): string
    {
        $description = (string) ($meetupEvent->description ?? '');

        if (! $meetupEvent->isCancelled()) {
            return $description;
        }

        return $description === ''
            ? self::CANCELLED_CONTENT_NOTICE
            : self::CANCELLED_CONTENT_NOTICE."\n\n".$description;
    }

    /**
     * The machine-readable half of a cancellation (issue #141), or nothing at all.
     *
     * THE SAME FACT IS WRITTEN TWICE, and the two are not redundant:
     *
     *  - `["status", "canceled"]` is the readable form. `status` is the word NIP-52
     *    itself uses for the state of a calendar record (52.md, kind 31925), so a human
     *    reading the raw event needs no lookup table. It is multi-letter, though, and
     *    NIP-01 §"Tags" makes only single-letter keys indexable — "as a convention, all
     *    single-letter (only english alphabet letters: a-z, A-Z) key tags are expected
     *    to be indexed by relays" — so nobody can FILTER on it.
     *  - `["L", "status"]` + `["l", "canceled", "status"]` is the filterable form.
     *    NIP-32 defines `L` as a label namespace and `l` as a label carrying that
     *    namespace as its mark, and says in so many words that "`l` and `L` tags MAY be
     *    added to other event kinds to support self-reporting … labels refer to the
     *    event itself" — which is exactly this case, an organiser labelling their own
     *    record. Both are single-letter, and NIP-01 indexes the FIRST value of a tag, so
     *    `{"kinds":[31923],"#l":["canceled"]}` is a query a relay can actually answer.
     *    Verified against nostr-protocol/nips at c3fd9af17939316bf6d0d83a5759100f8b0a1bdb
     *    (2026-09-04), 01.md line 84 and 32.md.
     *
     * NIP-32 asks (SHOULD, not MUST) that a namespace be unambiguous "such as an ISO
     * standard or reverse domain name notation". `status` is neither, and that is the
     * owner's decision taken with the trade-off visible: a reverse-DNS namespace of ours
     * would be unambiguous and unguessable, and a label nobody can guess is one nobody
     * filters on. The value stays inside the `status` namespace, so a reader that does
     * not know it can see what it claims to be.
     *
     * THE ONE KNOWN COLLISION, measured rather than assumed, and accepted with its cost
     * stated. The same nips PR #2351 puts `L`/`l` on these very kinds for something
     * else: `["L","rrule"]` + `["l","<RRULE string>"]`. Its client pseudo-code reads
     * `rruleStr = event.tags["l"]` and decides recurrence by "`L`/`l` tags present",
     * WITHOUT checking the mark — so a client that implemented that draft literally
     * would read our cancellation label as the recurrence rule `canceled` and try to
     * parse it as RFC 5545. Three things keep this from being a reason not to ship:
     * the PR is unmerged and nothing implements it; its own examples emit `["l", "…"]`
     * with no mark at all, which NIP-32 forbids ("`l` tags MUST include a mark matching
     * an `L` tag value"), so a reader who follows NIP-32 rather than that pseudo-code
     * separates the two namespaces correctly; and an RRULE parser handed `canceled`
     * fails rather than silently mis-renders. IF #2351 EVER MERGES IN THAT SHAPE, this
     * namespace is the thing to re-examine — a reverse-DNS one would end the ambiguity
     * at the price named above.
     *
     * NOTHING IS EMITTED FOR AN EVENT THAT IS ON. An absent tag is the "no tag beats a
     * wrong tag" rule the rest of this class follows, and it has a second consequence
     * that matters here: every event already published carries no `status` tag, so this
     * change does not move one single fingerprint in the back catalogue. Only a
     * cancellation does.
     *
     * @return list<list<string>>
     */
    private static function cancellationTags(MeetupEvent $meetupEvent): array
    {
        if (! $meetupEvent->isCancelled()) {
            return [];
        }

        return [
            ['status', self::CANCELLED_STATUS],
            ['L', self::CANCELLED_LABEL_NAMESPACE],
            ['l', self::CANCELLED_STATUS, self::CANCELLED_LABEL_NAMESPACE],
        ];
    }

    /**
     * The `t` topic tags for a meetup's location (issue #69).
     *
     * Order is the reporter's: the two static topics, then the geography from most to
     * least specific — city, region name, `<region>-<country>`, region code, country
     * code. For Indianapolis: `bitcoin, meetup, indianapolis, indiana, in-us, in, us`.
     *
     * `in-us` is region-first and lowercase, i.e. NOT the ISO 3166-2 code that
     * `App\Models\Region::isoCode()` returns (`US-IN`). That is the form asked for
     * in the issue, and NIP-24 forbids the uppercase one outright; the ISO code is not
     * emitted in addition, because a `t` tag nobody searches for is dead weight on every
     * event we ever publish.
     *
     * ## Normalisation
     *
     * NIP-24: "`t`: a hashtag. The value MUST be a lowercase string." On top of the
     * lowercasing, every value is trimmed and every run of characters that is neither a
     * letter, a digit nor a combining mark collapses into a single `-`, with leading and
     * trailing separators dropped. So `New York` is published as `new-york` and
     * `St. Gallen` as `st-gallen`. A hyphen, not a bare join: it is the separator the
     * issue's own `in-us` uses, and it keeps the two words legible.
     *
     * A part that normalises to the empty string contributes NO tag — that is what makes
     * an incomplete address emit fewer tags instead of `['t', '']`. `<region>-<country>`
     * needs both halves and disappears if either is missing; the bare codes do not.
     *
     * ## Non-ASCII names: both forms, on purpose
     *
     * `München`, `Łódź`, `Székesfehérvár`, `Plzeň` and `Rīga` are real inputs here, and a
     * `t` tag is matched byte for byte — relays index the string, they do not fold it.
     * Folding to ASCII only would drop the form a local actually types; keeping only the
     * native form would drop everyone whose keyboard or search box does not produce it.
     * Both are emitted, native first: `münchen` + `munchen`, `łódź` + `lodz`. Where the
     * fold changes nothing (`berlin`) exactly one tag is emitted, and where two parts
     * agree the duplicate is dropped.
     *
     * The fold is {@see Str::ascii()} with its DEFAULT language, deliberately not the
     * request locale, so that the tag is a property of the city rather than of whoever
     * happened to trigger the publish. `Str::ascii($value, 'de')` returns `muenchen`
     * where the default returns `munchen`; these events are replaceable and get
     * re-published, so a tag that moves with the caller's language would split one city
     * across two hashtags over time.
     *
     * A CORRECTION to what stood here first, because the reasoning was wrong even though
     * the code is right: this docblock claimed `app.locale` is mutated under this path,
     * citing PublishUnpublishedItems::configureForCountry(). That command is
     * `nostr:publish` — a different scheduler entry, and therefore a different process,
     * from the `nostr:publish-calendar` command that reaches this method. A config()
     * mutation lives in one process's memory and cannot leak into a later independent
     * run (there is no Octane here), so app()->getLocale() is in fact CONSTANT on this
     * path. The decision stands on the paragraph above, not on that claim.
     *
     * @return list<string>
     */
    private static function topicTags(?City $city): array
    {
        $regionCode = self::nonEmpty($city?->region?->code);
        $countryCode = self::nonEmpty($city?->country?->code);

        /*
         * The `!== null` pair on the combined value is belt-and-braces, and measured as
         * such: removing it leaves every test in `NostrCalendarTopicTagsTest` green,
         * because a half-empty `"in-"` normalises to `in` and the dedupe below then drops
         * it against the bare region code. It stays because the requirement is "both
         * halves or nothing", and that must not rest on two unrelated rules downstream
         * happening to cancel the mistake out.
         */
        $sources = [
            $city?->name,
            $city?->region?->name,
            $regionCode !== null && $countryCode !== null ? "{$regionCode}-{$countryCode}" : null,
            $regionCode,
            $countryCode,
        ];

        $topics = self::STATIC_TOPICS;

        foreach ($sources as $source) {
            foreach (self::topicVariants($source) as $topic) {
                $topics[] = $topic;
            }
        }

        // Two parts can normalise to the same value — a city named after its region
        // ("New York, New York"), or India's country code next to Indiana's region code.
        // A repeated `t` tag is legal and pointless; first occurrence wins, which keeps
        // the most-specific-first order intact.
        return array_values(array_unique($topics));
    }

    /**
     * The one or two forms a single address part contributes: the normalised value and,
     * when it differs, its ASCII fold. Nothing in, nothing out.
     *
     * @return list<string>
     */
    private static function topicVariants(?string $value): array
    {
        $native = self::normaliseTopic((string) $value);

        if ($native === '') {
            return [];
        }

        $folded = self::normaliseTopic(Str::ascii((string) $value));

        return $folded === '' || $folded === $native ? [$native] : [$native, $folded];
    }

    private static function normaliseTopic(string $value): string
    {
        /*
         * `\p{M}` is in the keep set on purpose. Without it a DECOMPOSED "Mu" + U+0308
         * would lose its combining diaeresis to the separator and read `mu-nchen` — the
         * one input shape where a Unicode-aware regex silently produces nonsense.
         *
         * `?? ''` is not decoration either: preg_replace returns null on malformed UTF-8,
         * and a null cast to string would emit `['t', '']`. No tag beats an empty one.
         */
        $separated = preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '-', mb_strtolower(trim($value)));

        return trim($separated ?? '', '-');
    }

    private static function nonEmpty(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function geohashFor(mixed $lat, mixed $lon): ?string
    {
        if ($lat === null || $lon === null) {
            return null;
        }

        return GeoHash::encode((float) $lat, (float) $lon, 5);
    }

    private static function meetupLocation(Meetup $meetup): ?string
    {
        $city = $meetup->city;
        if (! $city) {
            return null;
        }

        $parts = collect([$city->name, $city->country?->name])->filter();

        return $parts->isEmpty() ? null : $parts->implode(', ');
    }

    /**
     * @return list<string>
     */
    private static function socialLinks(Meetup $meetup): array
    {
        $links = [];

        if ($meetup->webpage) {
            $links[] = $meetup->webpage;
        }

        if ($meetup->telegram_link) {
            $links[] = $meetup->telegram_link;
        }

        if ($meetup->twitter_username) {
            $links[] = 'https://twitter.com/'.$meetup->twitter_username;
        }

        if ($meetup->nostr && str_starts_with($meetup->nostr, 'npub1')) {
            $links[] = 'nostr:'.$meetup->nostr;
        }

        return $links;
    }
}
