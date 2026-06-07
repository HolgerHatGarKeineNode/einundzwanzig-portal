<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMeetupEventRequest;
use App\Http\Requests\Api\UpdateMeetupEventRequest;
use App\Http\Resources\MeetupEventResource;
use App\Models\MeetupEvent;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Meetup-Event anlegen
     *
     * Erlaubt einem authentifizierten Nutzer, ein Meetup-Event programmatisch anzulegen.
     * Der Ersteller (created_by) wird automatisch gesetzt.
     */
    #[ResponseAttribute(status: 401, description: 'Nicht authentifiziert.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function store(StoreMeetupEventRequest $request): JsonResponse
    {
        $meetupEvent = MeetupEvent::create($request->validated());

        return MeetupEventResource::make($meetupEvent->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Meetup-Event aktualisieren
     *
     * Aktualisiert ein Meetup-Event; nur fuer den Ersteller oder einen Super-Admin.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf das Meetup-Event aendern.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function update(UpdateMeetupEventRequest $request, MeetupEvent $meetupEvent): MeetupEventResource
    {
        $meetupEvent->update($request->validated());

        return MeetupEventResource::make($meetupEvent->fresh());
    }

    /**
     * Eigene Meetup-Events auflisten
     *
     * Liefert alle vom authentifizierten Nutzer erstellten Meetup-Events, nach Startzeit absteigend sortiert.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MeetupEvent::class);

        $meetupEvents = MeetupEvent::query()
            ->where('created_by', $request->user()->id)
            ->orderByDesc('start')
            ->get();

        return MeetupEventResource::collection($meetupEvents);
    }

    /**
     * Eigenes Meetup-Event anzeigen
     *
     * Zeigt ein einzelnes, vom authentifizierten Nutzer erstelltes Meetup-Event.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf das Meetup-Event sehen.')]
    public function mineShow(MeetupEvent $meetupEvent): MeetupEventResource
    {
        Gate::authorize('view', $meetupEvent);

        return MeetupEventResource::make($meetupEvent);
    }
}
