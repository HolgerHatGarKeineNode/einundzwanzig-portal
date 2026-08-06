<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use App\Support\VereinMeetupGate;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

#[Group(name: 'Meetups', weight: 3)]
class MeetupMapController extends Controller
{
    /**
     * Public meetups for the community map
     *
     * Returns all meetups visible on the map with geo and contact data.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[QueryParameter(name: 'withIntro', description: 'Presence flag: when present, the intro text is included.', required: false, type: 'string')]
    #[QueryParameter(name: 'withLogos', description: 'Presence flag: when present, the logo URL is included.', required: false, type: 'string')]
    public function __invoke(Request $request, VereinMeetupGate $gate): Collection
    {
        // Once per request: the (cached) set of ids of the association-member-gated
        // meetups. This lets the app check room existence without relay access.
        $gatedIds = $gate->gatedMeetupIds()->all();

        return Meetup::query()
            ->where('visible_on_map', true)
            ->with([
                'meetupEvents',
                'city.country',
                'media',
            ])
            ->get()
            ->map(fn ($meetup) => [
                // Stable numeric DB id as binding key (additive, non-breaking).
                'id' => $meetup->id,
                // true if association-member-gated (= has a Nostr room).
                'has_room' => in_array($meetup->id, $gatedIds, true),
                'name' => $meetup->name,
                'portalLink' => url()->route(
                    'meetups.landingpage',
                    ['country' => $meetup->city->country, 'meetup' => $meetup],
                ),
                'url' => $meetup->telegram_link ?? $meetup->webpage,
                'top' => $meetup->github_data['top'] ?? null,
                'left' => $meetup->github_data['left'] ?? null,
                'country' => str($meetup->city->country->code)->upper(),
                'state' => $meetup->github_data['state'] ?? null,
                'city' => $meetup->city->name,
                'longitude' => (float) $meetup->city->longitude,
                'latitude' => (float) $meetup->city->latitude,
                'twitter_username' => $meetup->twitter_username,
                'website' => $meetup->webpage,
                'simplex' => $meetup->simplex,
                'signal' => $meetup->signal,
                'nostr' => $meetup->nostr,
                'rsvp_enabled' => $meetup->rsvp_enabled,
                'attendees_public' => $meetup->attendees_public,
                'next_event' => $meetup->nextEvent,
                'intro' => $request->has('withIntro') ? $meetup->intro : null,
                'logo' => $request->has('withLogos') ? $meetup->getFirstMediaUrl('logo') : null,
            ]);
    }
}
