<?php

namespace App\Http\Controllers\Api;

use App\Actions\MeetupEvents\CreateMeetupEventSeries;
use App\Enums\RsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RsvpMeetupEventRequest;
use App\Http\Requests\Api\StoreMeetupEventRequest;
use App\Http\Requests\Api\UpdateMeetupEventRequest;
use App\Http\Resources\MeetupEventResource;
use App\Http\Resources\TagResource;
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
     * The one description behind every `locale` query parameter of this controller.
     *
     * Five endpoints honour the parameter — the public list, `store`, `update`, `mine`
     * and `mineShow` — and the API reference used to advertise it on the list alone
     * (issue #57). Held as a constant rather than copied five times so the reference
     * cannot start describing the same parameter in five slightly different ways.
     */
    private const LOCALE_PARAMETER_DESCRIPTION = 'Requested language for tag names (ISO 639-1, e.g. "cs"). Falls back to the Accept-Language header, then to the existing display-chain default. The language you actually get is in each tag\'s `locale` field, which can differ when the tag has no name in the requested language.';

    /**
     * List meetup events
     *
     * Returns upcoming/past meetup events. With an optional date, the result is filtered
     * to the month of that date.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[PathParameter(name: 'date', description: 'Optional date (Y-m-d); filters to the month of that date.', required: false, type: 'string')]
    #[QueryParameter(name: 'locale', description: self::LOCALE_PARAMETER_DESCRIPTION, required: false, type: 'string')]
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
            /*
             * DEPRECATED (issue #71), and kept anyway: `Y-m-d H:i` in UTC with no zone
             * marker and no seconds. A consumer reading `2026-09-16 17:00` cannot tell
             * UTC from the organiser's zone from its own.
             *
             * Replaced by `start_iso` / `end_iso` below. These two stay unchanged, byte
             * for byte, until the live mobile client has moved over — removing them is a
             * breaking change and belongs to a coordinated client release, not here.
             * Until then both pairs describe the SAME instant, so a client can migrate
             * one field at a time without any shift in what it renders.
             *
             * Still a raw ->format() that skips the App\Support\Carbon user-timezone
             * conversion on purpose, because an API has no "current user's timezone".
             * MobileMeetupListController::__invoke() mirrors this format on purpose.
             */
            'start' => $event->start->format('Y-m-d H:i'),
            'end' => $event->end?->format('Y-m-d H:i'),
            /*
             * The zone-marked replacement for `start` / `end` (issue #71):
             * `2026-09-16T17:00:00+00:00` — ISO 8601 with a numeric OFFSET, not the `Z`
             * shorthand, because that is what `toIso8601String()` emits and what this
             * codebase already puts on the wire elsewhere (WebhookSubscriptionResource).
             * One spelling across the API beats picking the prettier one here.
             *
             * ->setTimezone('UTC') is explicit rather than incidental. The datetime cast
             * already yields UTC on this route (config('app.timezone') is 'UTC', and
             * SetTimezone runs on the web middleware group only — never on an API
             * request), so both fields name the same wall clock. Spelling the conversion
             * out makes the +00:00 offset a promise of this endpoint instead of a side
             * effect of that configuration.
             *
             * MobileMeetupListController::__invoke() emits the same format under the same
             * `_iso` naming — both endpoints move together, per issue #71.
             */
            'start_iso' => $event->start->setTimezone('UTC')->toIso8601String(),
            'end_iso' => $event->end?->setTimezone('UTC')->toIso8601String(),
            /*
             * The venue in up to two layers, and the six osm_* keys are ALWAYS present
             * — null when no map place was picked, never absent (Issue #37 follow-up).
             * A typed client sees one stable shape and does not have to distinguish
             * "this event has no map place" from "this endpoint does not serve them".
             * Same fields, same names and same string-typed coordinates as
             * MeetupEventResource, so one type covers both.
             */
            'location' => $event->location,
            'osm_type' => $event->osm_type,
            'osm_id' => $event->osm_id,
            'osm_name' => $event->osm_name,
            'osm_address' => $event->osm_address,
            'osm_lat' => $event->osm_lat,
            'osm_lon' => $event->osm_lon,
            'description' => $event->description,
            /*
             * DEPRECATED (issue #70) and kept for the same reason `start` above is: the
             * first of `links`, and the only one this field can ever show. Removing it
             * is a breaking change for the live mobile client, so both ship side by
             * side until that client has moved to `links`.
             */
            'link' => $event->link,
            // Every link of this event, in the organiser's order, each with an optional
            // `label` (null when there is none). Same shape and same key as
            // MeetupEventResource, so one client type covers both endpoints.
            'links' => $event->linkList(),
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
     * List meetup events
     *
     * Returns every upcoming/past meetup event, unfiltered. The same list
     * `GET /meetup-events/{date}` returns when no date is given.
     *
     * EXISTS FOR THE API REFERENCE, NOT FOR THE BEHAVIOUR (issue #57). The route used
     * to be `meetup-events/{date?}` alone, and Scramble collapses an optional path
     * parameter — it rewrites the URI with `Str::replace('?}', '}', …)` before building
     * the operation, so the document held `/meetup-events/{date}` and nothing at all
     * for the path consumers actually call. A route of its own is the only way to get
     * that path generated rather than synthesised into the document afterwards.
     *
     * A SEPARATE METHOD rather than a second route onto `__invoke()`: the attributes
     * are per method, so sharing one would put `#[PathParameter(name: 'date')]` on a
     * path that has no `{date}` placeholder — invalid OpenAPI, measured. The body
     * delegates, so there is exactly one implementation and the two paths cannot drift.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[QueryParameter(name: 'locale', description: self::LOCALE_PARAMETER_DESCRIPTION, required: false, type: 'string')]
    public function index(Request $request): Collection
    {
        return $this->__invoke($request);
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
    #[QueryParameter(name: 'locale', description: self::LOCALE_PARAMETER_DESCRIPTION, required: false, type: 'string')]
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
    #[QueryParameter(name: 'locale', description: self::LOCALE_PARAMETER_DESCRIPTION, required: false, type: 'string')]
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
    #[QueryParameter(name: 'locale', description: self::LOCALE_PARAMETER_DESCRIPTION, required: false, type: 'string')]
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
    #[QueryParameter(name: 'locale', description: self::LOCALE_PARAMETER_DESCRIPTION, required: false, type: 'string')]
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
     * The rule itself lives on {@see TagResource::requestedLocale()}, because every
     * OTHER tag-bearing response resolves it there — this endpoint is the one that
     * hand-builds its tag array instead of going through the resource, and a second
     * copy of the precedence rule would be a second thing to keep in step.
     *
     * Absent both `?locale=` and `Accept-Language` this is null, and
     * `Tag::displayName()`/`displayLocale()` fall back to `app()->getLocale()` —
     * which on this route group is `config('app.locale')` (German), NOT English:
     * `SetApiLocale` switches the translator only and deliberately leaves
     * `app.locale` alone (see that middleware's docblock on slug transliteration).
     */
    private function resolveRequestedLocale(Request $request): ?string
    {
        return TagResource::requestedLocale($request);
    }
}
