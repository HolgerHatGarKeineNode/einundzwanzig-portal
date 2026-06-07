<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetupEvent;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Support\Collection;

#[Group(name: 'Meetups', weight: 3)]
class MeetupEventController extends Controller
{
    /**
     * Meetup-Termine auflisten
     *
     * Liefert kommende/vergangene Meetup-Termine. Mit optionalem Datum wird auf den
     * jeweiligen Monat dieses Datums gefiltert.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[PathParameter(name: 'date', description: 'Optionales Datum (Y-m-d); filtert auf den Monat dieses Datums.', required: false, type: 'string')]
    public function __invoke(?string $date = null): Collection
    {
        if ($date) {
            $date = Carbon::parse($date);
        }
        $events = MeetupEvent::query()
            ->with([
                'meetup.city.country',
                'meetup.media',
            ])
            ->when(
                $date,
                fn ($query) => $query
                    ->where('start', '>=', $date)
                    ->where('start', '<=', $date->copy()->endOfMonth()),
            )
            ->get();

        return $events->map(fn ($event) => [
            'start' => $event->start->format('Y-m-d H:i'),
            'location' => $event->location,
            'description' => $event->description,
            'link' => $event->link,
            'meetup.name' => $event->meetup->name,
            'meetup.portalLink' => url()->route(
                'meetups.landingpage',
                [
                    'country' => $event->meetup->city->country,
                    'meetup' => $event->meetup,
                ],
            ),
            'meetup.url' => $event->meetup->telegram_link ?? $event->meetup->webpage,
            'meetup.country' => str($event->meetup->city->country->code)->upper(),
            'meetup.city' => $event->meetup->city->name,
            'meetup.longitude' => (float) $event->meetup->city->longitude,
            'meetup.latitude' => (float) $event->meetup->city->latitude,
            'meetup.twitter_username' => $event->meetup->twitter_username,
            'meetup.website' => $event->meetup->webpage,
            'meetup.simplex' => $event->meetup->simplex,
            'meetup.signal' => $event->meetup->signal,
            'meetup.nostr' => $event->meetup->nostr,
            'meetup.logo' => $event->meetup->getFirstMediaUrl('logo'),
        ],
        );
    }
}
