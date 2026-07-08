<?php

namespace App\Http\Controllers\Api;

use App\Actions\MeetupEvents\CreateMeetupEventSeries;
use App\Enums\RsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RsvpMeetupEventRequest;
use App\Http\Requests\Api\StoreMeetupEventRequest;
use App\Http\Requests\Api\UpdateMeetupEventRequest;
use App\Http\Resources\MeetupEventResource;
use App\Models\MeetupEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
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
    #[ResponseAttribute(status: 400, description: 'Das übergebene Datum ist nicht parsebar (erwartet wird Y-m-d).')]
    public function __invoke(?string $date = null): Collection
    {
        if ($date) {
            try {
                $date = Carbon::parse($date);
            } catch (InvalidFormatException) {
                abort(Response::HTTP_BAD_REQUEST, 'Ungültiges Datum. Erwartet wird das Format Y-m-d.');
            }
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
            'id' => $event->id,
            'start' => $event->start->format('Y-m-d H:i'),
            'location' => $event->location,
            'description' => $event->description,
            'link' => $event->link,
            // null = Teilnehmerzahl ist für dieses Meetup nicht öffentlich (attendees_public=false).
            'attendees' => $event->meetup->attendees_public ? $event->attendeesCount() : null,
            'might_attendees' => $event->meetup->attendees_public ? $event->mightAttendeesCount() : null,
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
            'meetup.rsvp_enabled' => $event->meetup->rsvp_enabled,
        ],
        );
    }

    /**
     * Meetup-Event anlegen
     *
     * Erlaubt einem authentifizierten Nutzer, ein Meetup-Event programmatisch anzulegen.
     * Der Ersteller (created_by) wird automatisch gesetzt.
     *
     * Werden sowohl `recurrence_type` als auch `recurrence_end_date` übergeben, wird – wie im
     * Web-Editor – eine Serie einzelner Termine erzeugt (gemeinsame Expansions-Action, harte
     * Obergrenze von 100 Terminen) und die Antwort enthält die Liste aller erstellten Events.
     * Ohne diese Felder entsteht ein einzelner Termin.
     */
    #[ResponseAttribute(status: 401, description: 'Nicht authentifiziert.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function store(StoreMeetupEventRequest $request, CreateMeetupEventSeries $createSeries): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['recurrence_type']) && ! empty($validated['recurrence_end_date'])) {
            $events = $createSeries->handle($validated);

            return MeetupEventResource::collection($events)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        $meetupEvent = MeetupEvent::create($validated);

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
     * Bearbeitbare Meetup-Events auflisten
     *
     * Liefert alle Meetup-Events, die der authentifizierte Nutzer bearbeiten darf
     * (selbst angelegt ODER Leader des zugehörigen Meetups), nach Startzeit absteigend sortiert.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MeetupEvent::class);

        $meetupEvents = MeetupEvent::query()
            ->editableBy($request->user()->id)
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

    /**
     * RSVP-Status eines Termins anzeigen
     *
     * Liefert den eigenen Teilnahme-Status des authentifizierten Nutzers für
     * diesen Termin sowie die aktuellen Zähler der Zu- und Vielleicht-Sagen.
     */
    public function rsvpStatus(Request $request, MeetupEvent $meetupEvent): JsonResponse
    {
        return response()->json($this->rsvpPayload($meetupEvent, $request->user()));
    }

    /**
     * Für einen Termin zu- oder absagen
     *
     * Trägt den authentifizierten Nutzer als Teilnehmer („attending"),
     * Vielleicht-Teilnehmer („maybe") oder gar nicht („none", = absagen) ein.
     * Der Anzeigename wird automatisch aus dem Profil übernommen. Idempotent:
     * derselbe Status mehrfach gesetzt verändert nichts.
     *
     * Ist die Anmeldung für das zugehörige Meetup deaktiviert (`rsvp_enabled`=false),
     * wird die Anfrage mit 422 abgelehnt. Die zurückgegebenen Zähler sind `null`,
     * wenn die Teilnehmerliste für den Betrachter nicht sichtbar ist
     * (`attendees_public`=false und kein Verwalter).
     */
    #[ResponseAttribute(status: 401, description: 'Nicht authentifiziert.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler (unbekannter Status) oder Anmeldung für dieses Meetup deaktiviert.')]
    public function rsvp(RsvpMeetupEventRequest $request, MeetupEvent $meetupEvent): JsonResponse
    {
        abort_if(
            ! $meetupEvent->meetup->rsvp_enabled,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            __('Die Anmeldung ist für dieses Meetup deaktiviert.'),
        );

        $user = $request->user();
        $status = RsvpStatus::from($request->validated('status'));

        $meetupEvent->setRsvpFor($user, $status, (string) $user->name);

        return response()->json($this->rsvpPayload($meetupEvent->fresh(), $user));
    }

    /**
     * Einheitliche RSVP-Antwort: eigener Status + aktuelle Zähler. Die Zähler sind
     * null, wenn die Teilnehmerliste für den Betrachter nicht sichtbar ist
     * (attendees_public=false und kein Verwalter).
     *
     * @return array{status: string, attendees: int|null, might_attendees: int|null}
     */
    private function rsvpPayload(MeetupEvent $meetupEvent, User $user): array
    {
        $countsVisible = $meetupEvent->meetup->attendeesVisibleTo($user);

        return [
            'status' => $meetupEvent->rsvpStatusFor($user)->value,
            'attendees' => $countsVisible ? $meetupEvent->attendeesCount() : null,
            'might_attendees' => $countsVisible ? $meetupEvent->mightAttendeesCount() : null,
        ];
    }
}
