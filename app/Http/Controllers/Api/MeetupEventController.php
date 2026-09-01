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
use Dedoc\Scramble\Attributes\QueryParameter;
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
     * List meetup events
     *
     * Returns upcoming/past meetup events. With an optional date, the result is filtered
     * to the month of that date.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[PathParameter(name: 'date', description: 'Optional date (Y-m-d); filters to the month of that date.', required: false, type: 'string')]
    #[QueryParameter(name: 'locale', description: 'Requested language for tag names (ISO 639-1, e.g. "cs"). Falls back to the Accept-Language header, then to the existing display-chain default.', required: false, type: 'string')]
    #[ResponseAttribute(status: 400, description: 'The given date cannot be parsed (Y-m-d is expected).')]
    public function __invoke(Request $request, ?string $date = null): Collection
    {
        $requestedLocale = $this->resolveRequestedLocale($request);

        if ($date) {
            try {
                $date = Carbon::parse($date);
            } catch (InvalidFormatException) {
                abort(Response::HTTP_BAD_REQUEST, __('Ungültiges Datum. Erwartet wird das Format Y-m-d.'));
            }
        }
        $events = MeetupEvent::query()
            ->with([
                'meetup.city.country',
                'meetup.media',
                // Without this the resource's whenLoaded('tags') stays silent and the
                // field disappears from the payload rather than showing up empty.
                'tags',
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
            'title' => $event->title,
            'start' => $event->start->format('Y-m-d H:i'),
            'end' => $event->end?->format('Y-m-d H:i'),
            'location' => $event->location,
            'description' => $event->description,
            'link' => $event->link,
            // Names resolved through the display chain for the requested locale, so a
            // tag that exists only in German still reads as something rather than as
            // an empty string when the client asked for e.g. Czech.
            'tags' => $event->tags->map(fn ($tag) => [
                'name' => $tag->displayName($requestedLocale),
                'locale' => $tag->displayLocale($requestedLocale),
            ])->all(),
            // null = the attendee count is not public for this meetup (attendees_public=false).
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
     * Create meetup event
     *
     * Allows an authenticated user to create a meetup event programmatically.
     * The creator (created_by) is set automatically.
     *
     * If both `recurrence_type` and `recurrence_end_date` are passed, a series of individual
     * meetup events is created, just like in the web editor (shared expansion action, hard
     * upper limit of 100 meetup events), and the response contains the list of all created events.
     * Without these fields, a single meetup event is created.
     */
    #[ResponseAttribute(status: 401, description: 'Not authenticated.')]
    #[ResponseAttribute(status: 422, description: 'Validation error.')]
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

        return MeetupEventResource::make($meetupEvent->fresh()->load('tags'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update meetup event
     *
     * Updates a meetup event; only for the creator or a super admin.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may change the meetup event.')]
    #[ResponseAttribute(status: 422, description: 'Validation error.')]
    public function update(UpdateMeetupEventRequest $request, MeetupEvent $meetupEvent): MeetupEventResource
    {
        $meetupEvent->update($request->validated());

        return MeetupEventResource::make($meetupEvent->fresh()->load('tags'));
    }

    /**
     * List editable meetup events
     *
     * Returns all meetup events the authenticated user may edit
     * (created by themselves OR leader of the associated meetup), sorted by start time descending.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MeetupEvent::class);

        $meetupEvents = MeetupEvent::query()
            ->editableBy($request->user()->id)
            ->with('tags')
            ->orderByDesc('start')
            ->get();

        return MeetupEventResource::collection($meetupEvents);
    }

    /**
     * Show own meetup event
     *
     * Shows a single meetup event created by the authenticated user.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may view the meetup event.')]
    public function mineShow(MeetupEvent $meetupEvent): MeetupEventResource
    {
        Gate::authorize('view', $meetupEvent);

        return MeetupEventResource::make($meetupEvent->load('tags'));
    }

    /**
     * Show the RSVP status of a meetup event
     *
     * Returns the authenticated user's own RSVP status for this meetup event
     * as well as the current counters of attending and maybe responses.
     */
    public function rsvpStatus(Request $request, MeetupEvent $meetupEvent): JsonResponse
    {
        return response()->json($this->rsvpPayload($meetupEvent, $request->user()));
    }

    /**
     * RSVP to a meetup event
     *
     * Records the authenticated user as attending ("attending"),
     * maybe attending ("maybe") or not at all ("none", = declining).
     * The display name is taken from the profile automatically. Idempotent:
     * setting the same status repeatedly changes nothing.
     *
     * If RSVP is disabled for the associated meetup (`rsvp_enabled`=false),
     * the request is rejected with 422. The returned counters are `null`
     * if the attendee list is not visible to the viewer
     * (`attendees_public`=false and not a manager).
     */
    #[ResponseAttribute(status: 401, description: 'Not authenticated.')]
    #[ResponseAttribute(status: 422, description: 'Validation error (unknown status) or RSVP disabled for this meetup.')]
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
     * Unified RSVP response: own status + current counters. The counters are
     * null if the attendee list is not visible to the viewer
     * (attendees_public=false and not a manager).
     *
     * @return array{status: string, attendees: int|null, might_attendees: int|null, attendee_names: list<string>|null}
     */
    private function rsvpPayload(MeetupEvent $meetupEvent, User $user): array
    {
        $countsVisible = $meetupEvent->meetup->attendeesVisibleTo($user);

        return [
            'status' => $meetupEvent->rsvpStatusFor($user)->value,
            'attendees' => $countsVisible ? $meetupEvent->attendeesCount() : null,
            'might_attendees' => $countsVisible ? $meetupEvent->mightAttendeesCount() : null,
            // Display names of the attendees without the `id_<userId>|` prefix. Same
            // visibility rule as the counters (attendees_public or manager).
            'attendee_names' => $countsVisible
                ? collect($meetupEvent->attendees ?? [])
                    ->map(fn (string $entry): string => str($entry)->after('|')->toString())
                    ->values()
                    ->all()
                : null,
        ];
    }

    /**
     * The language the client asked for a tag name in, or null to leave it to the
     * display chain's own default.
     *
     * `?locale=` takes precedence over `Accept-Language` because it is explicit and
     * survives a client that cannot set custom headers. Neither is required: absent
     * both, this returns null and `Tag::displayName()`/`displayLocale()` fall back to
     * `app()->getLocale()`, which `SetApiLocale` has already forced to English for
     * this route group — deliberately not overridden here, that middleware's own
     * reasoning still applies to every other string in the response.
     */
    private function resolveRequestedLocale(Request $request): ?string
    {
        $queryLocale = $request->query('locale');

        if (is_string($queryLocale) && $queryLocale !== '') {
            return $queryLocale;
        }

        $preferred = $request->getLanguages()[0] ?? null;

        return $preferred === null ? null : str($preferred)->before('_')->toString();
    }
}
