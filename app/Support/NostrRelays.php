<?php

namespace App\Support;

/**
 * The ONE place that answers "which relays" for the portal's Nostr publishing.
 *
 * Until 2026-11-17 every consumer read `config('services.nostr.relays')` on its own: the
 * two publish commands, the RSVP ingest, the naddr hints and the organiser's "published
 * to" line. Meanwhile the key's NIP-65 relay list (kind 10002) had been written by hand
 * in a client and named three relays, of which the portal wrote to one. A calendar client
 * that follows the outbox model reads exactly that list — so it asked two relays the
 * portal had never sent a single event to.
 *
 * Read the list through {@see self::all()}; {@see self::quorum()} is the rule for "done".
 */
final class NostrRelays
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::normaliseList((array) config('services.nostr.relays', []));
    }

    /**
     * How many relays must confirm an event with their own `OK true` before a record
     * counts as published. Capped at the number of relays, so a single-relay setup (and
     * every test with one fake relay) still publishes; never below one.
     */
    public static function quorum(): int
    {
        $configured = (int) config('services.nostr.publish_quorum', 2);

        return max(1, min($configured, count(self::all())));
    }

    /**
     * One spelling per relay: scheme and host in lower case, no trailing slash. NIP-65
     * lists written by clients usually carry the slash (`wss://nos.lol/`), env values
     * usually do not; compared as typed, the same relay would count twice.
     */
    public static function normalise(string $url): string
    {
        $url = trim($url);

        if (! preg_match('#^(wss?)://([^/]+)(/.*)?$#i', $url, $parts)) {
            return $url;
        }

        return strtolower($parts[1]).'://'.strtolower($parts[2]).rtrim($parts[3] ?? '', '/');
    }

    /**
     * @param  array<array-key, mixed>  $urls
     * @return list<string>
     */
    public static function normaliseList(array $urls): array
    {
        $normalised = [];

        foreach ($urls as $url) {
            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $normalised[self::normalise($url)] = true;
        }

        return array_keys($normalised);
    }
}
