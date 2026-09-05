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
 * Rein und ohne I/O — keine Signatur, keine Relay-Uebertragung, kein Speichern. Das
 * macht die Tag-Logik ohne Netzwerk oder Schluessel testbar; Signieren und Versenden
 * uebernimmt der Command (`nostr:publish-calendar`) mit {@see NostrEventTransmitter}.
 *
 * Kinds nach NIP-52 (github.com/nostr-protocol/nips/blob/master/52.md):
 *  - 31924 Calendar: einzige Pflicht-Tags sind `d` und `title`; `location`/`g`/`r`
 *    sind hier eine bewusste Erweiterung nach Issue #34 (kein Verstoss gegen die
 *    Spec — unbekannte Tags werden von Clients ignoriert).
 *  - 31923 Time-Based Calendar Event: `d`, `title`, `start` Pflicht; `D` (Tages-
 *    Granularitaet, floor(start / 86400)) ebenfalls Pflicht.
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
        $event = new Event;
        $event->setKind(self::KIND_CALENDAR);
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

        return $event;
    }

    public static function forMeetupEvent(MeetupEvent $meetupEvent, string $pubkeyHex): Event
    {
        $event = new Event;
        $event->setKind(self::KIND_TIME_BASED_EVENT);
        $event->setContent((string) ($meetupEvent->description ?? ''));
        $event->addTag(['d', self::eventDTag($meetupEvent)]);
        $event->addTag(['title', $meetupEvent->title ?: $meetupEvent->meetup->name]);

        $start = $meetupEvent->start->getTimestamp();
        $event->addTag(['start', (string) $start]);
        $event->addTag(['D', (string) intdiv($start, 86400)]);

        if ($meetupEvent->end) {
            $event->addTag(['end', (string) $meetupEvent->end->getTimestamp()]);
        }

        $event->addTag(['start_tzid', CountryTimezone::forCountryCode($meetupEvent->meetup->city?->country?->code)]);

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

        $event->addTag(['a', self::coordinate(
            self::KIND_CALENDAR,
            $pubkeyHex,
            self::calendarDTag($meetupEvent->meetup)
        )]);

        return $event;
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
     * request locale: `app.locale` is not a constant in this codebase — the sibling
     * publisher rewrites it per country
     * (`App\Console\Commands\Nostr\PublishUnpublishedItems::configureForCountry()`)
     * and {@see City::getSlugOptions()} already documents 37 slugs shifting with it. A
     * locale-dependent fold would emit `muenchen` in one run and `munchen` in the next
     * for the same city. These events are replaceable and get re-published, so their tags
     * must not depend on which record happened to go out before them.
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
