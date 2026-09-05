<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lean, fast meetup list for the mobile app.
 *
 * Deliberately separate from {@see MeetupMapController} (GET /api/meetups) so that the
 * website map and other consumers remain unchanged. Only the fields that
 * the app list and the app map render (name, slug, city, country, geo, logo,
 * next meetup event) — no intro, no socials, no RSVP counters.
 *
 * The speed gain comes from the query: the model's nextEvent accessor
 * fires several queries per meetup (next meetup event + two counters),
 * replaced here by ONE correlated subquery on the start of the next
 * meetup event. City/country/media are eager loaded — a constant number of queries
 * regardless of the meetup count (no N+1).
 */
#[Group(name: 'Meetups', weight: 3)]
class MobileMeetupListController extends Controller
{
    /**
     * Meetup list for the mobile app
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(): Collection
    {
        return Meetup::query()
            ->where('visible_on_map', true)
            ->select(['id', 'name', 'slug', 'city_id'])
            ->addSelect(['next_event_start' => MeetupEvent::query()
                ->select('start')
                ->whereColumn('meetup_id', 'meetups.id')
                ->where('start', '>=', now())
                ->orderBy('start')
                ->limit(1),
            ])
            ->with([
                'city:id,name,country_id,longitude,latitude',
                'city.country:id,code',
                'media',
            ])
            ->get()
            // Sort in PHP, not in SQL: ORDER BY on the subquery alias
            // fails on PostgreSQL (an alias is only allowed as a standalone key,
            // not inside an expression). As in the app: next meetup event first,
            // those without one last, then by name. As a "Y-m-d H:i:s" string,
            // next_event_start sorts lexicographically = chronologically.
            ->sortBy(fn (Meetup $meetup): string => sprintf(
                '%d|%s|%s',
                $meetup->next_event_start === null ? 1 : 0,
                (string) $meetup->next_event_start,
                mb_strtolower($meetup->name),
            ))
            ->values()
            ->map(fn (Meetup $meetup): array => [
                // Stable numeric DB id as binding key for consumers
                // (e.g. meetup rooms in the Nostr client). Additive, non-breaking.
                'id' => $meetup->id,
                'name' => $meetup->name,
                'slug' => $meetup->slug,
                'city' => $meetup->city?->name ?? '',
                'country' => str($meetup->city?->country?->code)->upper()->value(),
                'latitude' => (float) ($meetup->city?->latitude ?? 0),
                'longitude' => (float) ($meetup->city?->longitude ?? 0),
                // getFirstMedia (not getFirstMediaUrl): the 'logo' collection has
                // a fallback URL (country placeholder). Without a real logo the
                // app should show the initials avatar, so null instead of a placeholder URL.
                'logo' => $meetup->getFirstMedia('logo')?->getUrl(),
                /*
                 * DEPRECATED (issue #71), and kept anyway: `Y-m-d H:i` in UTC with no zone
                 * marker, same format as `start` on GET /api/meetup-events.
                 *
                 * Replaced by `next_event_start_iso` below. This one stays unchanged, byte
                 * for byte, until the live mobile client has moved over; dropping it is a
                 * breaking change and belongs to a coordinated client release. Both fields
                 * describe the same instant, so the client can switch when it is ready.
                 */
                'next_event_start' => $meetup->next_event_start
                    ? Carbon::parse($meetup->next_event_start)->format('Y-m-d H:i')
                    : null,
                /*
                 * The zone-marked replacement (issue #71): `2026-09-16T17:00:00+00:00` —
                 * ISO 8601 with a numeric OFFSET rather than the `Z` shorthand, because
                 * that is what `toIso8601String()` emits and what the rest of this API
                 * already sends. Identical field naming (`<field>_iso`) and identical
                 * format to `start_iso` / `end_iso` on GET /api/meetup-events: issue #71
                 * requires both endpoints to move together.
                 *
                 * The subquery hands us a bare `Y-m-d H:i:s` string carrying no zone of its
                 * own, so the zone goes INTO parse() rather than onto the result:
                 * `parse($v, 'UTC')` reads the value as UTC, which is how the column is
                 * stored, whereas a trailing ->setTimezone('UTC') would convert it and,
                 * under a non-UTC PHP default, shift it away from the deprecated field
                 * above. Same instant, two spellings — that is the whole migration path.
                 */
                'next_event_start_iso' => $meetup->next_event_start
                    ? Carbon::parse($meetup->next_event_start, 'UTC')->toIso8601String()
                    : null,
            ]);
    }
}
