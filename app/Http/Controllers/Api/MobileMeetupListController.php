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
                 * Same format as GET /api/meetup-events (see MeetupEventController),
                 * and deliberately left alone by the ISO 8601 work (issue #48):
                 * `Y-m-d H:i` in UTC, no zone marker, no user-timezone conversion.
                 * A published API contract with a live mobile consumer — changing its
                 * shape is a breaking change that needs its own decision, and it has to
                 * be taken for both endpoints at once.
                 */
                'next_event_start' => $meetup->next_event_start
                    ? Carbon::parse($meetup->next_event_start)->format('Y-m-d H:i')
                    : null,
            ]);
    }
}
