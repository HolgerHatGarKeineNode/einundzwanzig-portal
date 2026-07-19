<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

#[Group(name: 'Meetups', weight: 3)]
class MeetupMapController extends Controller
{
    /**
     * Öffentliche Meetups für die Community-Karte
     *
     * Liefert alle auf der Karte sichtbaren Meetups mit Geo- und Kontaktdaten.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[QueryParameter(name: 'withIntro', description: 'Presence-Flag: Bei Vorhandensein wird der Intro-Text mitgeliefert.', required: false, type: 'string')]
    #[QueryParameter(name: 'withLogos', description: 'Presence-Flag: Bei Vorhandensein wird die Logo-URL mitgeliefert.', required: false, type: 'string')]
    public function __invoke(Request $request): Collection
    {
        return Meetup::query()
            ->where('visible_on_map', true)
            ->with([
                'meetupEvents',
                'city.country',
                'media',
            ])
            ->get()
            ->map(fn ($meetup) => [
                // Stabile numerische DB-id als Bindungsschlüssel (additiv, non-breaking).
                'id' => $meetup->id,
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
