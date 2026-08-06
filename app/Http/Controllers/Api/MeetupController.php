<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMeetupRequest;
use App\Http\Requests\Api\UpdateMeetupRequest;
use App\Http\Requests\Api\UploadMediaRequest;
use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group(name: 'Meetups', weight: 3)]
class MeetupController extends Controller
{
    use FiltersNumericIds;

    #[ExcludeRouteFromDocs]
    public function ical()
    {
        abort(404);
    }

    /**
     * List own meetups
     *
     * Returns the meetups of the signed-in user (id, name, including city/country and profile image),
     * sorted alphabetically. Requires an authenticated session (otherwise 401).
     */
    #[QueryParameter(name: 'search', description: 'Substring search in the meetup or city name.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Loads exactly the given meetup IDs.', required: false, type: 'array')]
    #[Response(status: 401, description: 'Not authenticated.')]
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $myMeetupIds = $user->meetups->pluck('id');

        return Meetup::query()
            ->select('id', 'name', 'city_id', 'slug')
            ->with([
                'city.country',
            ])
            ->whereIn('id', $myMeetupIds->toArray())
            ->orderBy('name')
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$request->search}%")
                    ->orWhereHas('city',
                        fn (Builder $query) => $query->whereLike('cities.name', "%{$request->search}%")),
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id', $this->numericIds($request)),
                fn (Builder $query) => $query->limit(10),
            )
            ->get()
            ->map(function (Meetup $meetup) {
                $meetup->profile_image = $meetup->getFirstMediaUrl('logo', 'thumb');

                return $meetup;
            });
    }

    /**
     * Create meetup
     *
     * Allows an authenticated user to create a meetup programmatically.
     * The creator (created_by) is set automatically.
     */
    #[Response(status: 401, description: 'Not authenticated.')]
    #[Response(status: 422, description: 'Validation error.')]
    public function store(StoreMeetupRequest $request): JsonResponse
    {
        $meetup = Meetup::create($request->validated());

        return MeetupResource::make($meetup->fresh())
            ->response()
            ->setStatusCode(\Symfony\Component\HttpFoundation\Response::HTTP_CREATED);
    }

    /**
     * Update meetup
     *
     * Updates a meetup; only for the creator or a super admin.
     */
    #[Response(status: 403, description: 'Only the creator or a super admin may change the meetup.')]
    #[Response(status: 422, description: 'Validation error.')]
    public function update(UpdateMeetupRequest $request, Meetup $meetup): MeetupResource
    {
        $meetup->update($request->validated());

        return MeetupResource::make($meetup->fresh());
    }

    /**
     * List own meetups
     *
     * Returns the meetups the authenticated user selected in the dashboard
     * (meetup_user pivot, "My Meetups"), sorted alphabetically.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Meetup::class);

        $meetups = $request->user()
            ->meetups()
            ->with('media')
            ->orderBy('name')
            ->get();

        return MeetupResource::collection($meetups);
    }

    /**
     * Add an existing meetup to "My Meetups"
     *
     * Adds an already existing meetup to the "My Meetups" list of the authenticated
     * user (meetup_user pivot as member, is_leader=false). Idempotent: a meetup that has
     * already been added remains unchanged. The master data stays reserved for the creator.
     */
    #[Response(status: 401, description: 'Not authenticated.')]
    public function addToMine(Request $request, Meetup $meetup): JsonResponse
    {
        Gate::authorize('addToMine', $meetup);

        $wasAdded = $meetup->addMember($request->user());

        return MeetupResource::make($meetup)
            ->response()
            ->setStatusCode($wasAdded
                ? \Symfony\Component\HttpFoundation\Response::HTTP_CREATED
                : \Symfony\Component\HttpFoundation\Response::HTTP_OK);
    }

    /**
     * Remove a meetup from "My Meetups"
     *
     * Removes a meetup from the "My Meetups" list of the authenticated user
     * (detaches the meetup_user pivot membership). The master data of the meetup is
     * preserved — counterpart to addToMine(). Idempotent: if the meetup was not (or no
     * longer) assigned, the response stays 200 OK.
     */
    #[Response(status: 401, description: 'Not authenticated.')]
    public function removeFromMine(Request $request, Meetup $meetup): MeetupResource
    {
        Gate::authorize('removeFromMine', $meetup);

        $meetup->removeMember($request->user());

        return MeetupResource::make($meetup);
    }

    /**
     * Show own meetup
     *
     * Shows a single one of the meetups selected in the dashboard (meetup_user pivot).
     */
    #[Response(status: 403, description: 'Only the creator or a member (meetup_user pivot) may view the meetup.')]
    public function mineShow(Meetup $meetup): MeetupResource
    {
        Gate::authorize('viewMine', $meetup);

        return MeetupResource::make($meetup);
    }

    /**
     * Upload meetup logo
     *
     * Uploads a logo (multipart, field "file") into the singleFile collection "logo",
     * replacing an existing logo in the process. Only for the creator or a super admin.
     * The response contains the fresh logo URL.
     */
    #[Response(status: 403, description: 'Only the creator or a super admin may replace the logo.')]
    #[Response(status: 422, description: 'Validation error (not an image, wrong MIME type, too large or dimensions too large).')]
    public function uploadLogo(UploadMediaRequest $request, Meetup $meetup): MeetupResource
    {
        $meetup->addMedia($request->file('file')->getRealPath())
            ->usingName($meetup->name)
            ->toMediaCollection('logo');

        return MeetupResource::make($meetup->fresh());
    }
}
