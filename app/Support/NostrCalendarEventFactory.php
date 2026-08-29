<?php

namespace App\Support;

use App\Models\Meetup;
use App\Models\MeetupEvent;
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
 */
class NostrCalendarEventFactory
{
    private const KIND_CALENDAR = 31924;

    private const KIND_TIME_BASED_EVENT = 31923;

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

        $event->addTag(['a', self::coordinate(
            self::KIND_CALENDAR,
            $pubkeyHex,
            self::calendarDTag($meetupEvent->meetup)
        )]);

        return $event;
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
