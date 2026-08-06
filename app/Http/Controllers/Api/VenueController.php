<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVenueRequest;
use App\Http\Requests\Api\UpdateVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'Master Data', weight: 5)]
class VenueController extends Controller
{
    use FiltersNumericIds;

    /**
     * List and search venues
     *
     * Public endpoint; returns id, name and the associated city/country, sorted alphabetically.
     * Without 'selected' the list is capped at 10 items. Every venue additionally contains
     * 'flag' (SVG URL of the country flag) and 'description' (city + street).
     */
    #[QueryParameter(name: 'search', description: 'Substring search in the venue name.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Loads exactly the given venue IDs (bypasses the 10-item limit).', required: false, type: 'array')]
    #[QueryParameter(name: 'withDetails', description: 'Presence flag: lifts the 10-item limit.', required: false, type: 'string')]
    public function index(Request $request)
    {
        return Venue::query()
            ->with(['city:id,name,country_id', 'city.country:id,name,code'])
            ->select('id', 'name', 'city_id')
            ->orderBy('name')
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$request->search}%")
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id', $this->numericIds($request)),
                fn (Builder $query) => $request->exists('withDetails') ? $query : $query->limit(10)
            )
            ->get()
            ->map(function (Venue $venue) {
                $venue->flag = asset('vendor/blade-flags/country-'.$venue->city->country->code.'.svg');
                $venue->description = $venue->city->name.', '.$venue->street;

                return $venue;
            });
    }

    /**
     * Create a venue
     *
     * Allows an authenticated user to create a venue programmatically.
     * The creator (created_by) is set automatically.
     */
    #[ResponseAttribute(status: 401, description: 'Not authenticated.')]
    #[ResponseAttribute(status: 422, description: 'Validation error.')]
    public function store(StoreVenueRequest $request): JsonResponse
    {
        $venue = Venue::create($request->validated());

        return VenueResource::make($venue->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a venue
     *
     * Updates a venue; only for the creator or a super admin.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may change the venue.')]
    #[ResponseAttribute(status: 422, description: 'Validation error.')]
    public function update(UpdateVenueRequest $request, Venue $venue): VenueResource
    {
        $venue->update($request->validated());

        return VenueResource::make($venue->fresh());
    }

    /**
     * List own venues
     *
     * Returns all venues created by the authenticated user, sorted alphabetically.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Venue::class);

        $venues = Venue::query()
            ->where('created_by', $request->user()->id)
            ->orderBy('name')
            ->get();

        return VenueResource::collection($venues);
    }

    /**
     * Show own venue
     *
     * Shows a single venue created by the authenticated user.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may view the venue.')]
    public function mineShow(Venue $venue): VenueResource
    {
        Gate::authorize('view', $venue);

        return VenueResource::make($venue);
    }
}
