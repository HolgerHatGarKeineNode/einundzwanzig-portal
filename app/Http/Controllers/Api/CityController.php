<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCityRequest;
use App\Http\Requests\Api\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
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
class CityController extends Controller
{
    use FiltersNumericIds;

    /**
     * List and search cities
     *
     * Public endpoint; returns id, name and the associated country, sorted alphabetically. Without 'selected' the list is capped at 10 items.
     */
    #[QueryParameter(name: 'search', description: 'Substring search in the city name.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Loads exactly the given IDs.', required: false, type: 'array')]
    #[QueryParameter(name: 'withDetails', description: 'Presence flag: additionally returns country code and flag URL and lifts the 10-item limit.', required: false, type: 'string')]
    public function index(Request $request)
    {
        $withDetails = $request->exists('withDetails');

        return City::query()
            ->with([$withDetails ? 'country:id,name,code' : 'country:id,name'])
            ->select('id', 'name', 'country_id')
            ->orderBy('name')
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$request->search}%")
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id', $this->numericIds($request)),
                fn (Builder $query) => $withDetails ? $query : $query->limit(10)
            )
            ->get()
            ->map(function (City $city) use ($withDetails) {
                if ($withDetails) {
                    $city->flag = asset('vendor/blade-flags/country-'.$city->country->code.'.svg');
                }

                return $city;
            });
    }

    /**
     * Create a city
     *
     * Allows an authenticated user to create a city programmatically.
     * The creator (created_by) is set automatically.
     *
     * City names are **not** unique — real places share names (eight municipalities
     * called Neuenkirchen in Lower Saxony alone, six Georgetowns in Indiana). The city
     * is resolved on name **plus country**, or exactly on `osm_type` + `osm_id` when
     * you send them.
     *
     * - Exactly one match → it is returned with status 200.
     * - No match → the city is created with status 201.
     * - Several matches → **422**, listing the candidates. Pick one by its id, or send
     *   `osm_type` + `osm_id` to be exact. This endpoint never guesses.
     * - Creating a city while a place of that name already exists → **422**, unless you
     *   send an OpenStreetMap reference or set `confirm_duplicate`. A second Georgetown
     *   is allowed; it just has to be a decision rather than a side effect.
     */
    #[ResponseAttribute(status: 200, description: 'The city already existed and is returned unchanged.')]
    #[ResponseAttribute(status: 401, description: 'Not authenticated.')]
    #[ResponseAttribute(status: 422, description: 'Validation error.')]
    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = City::resolveOrCreate($request->validated());

        $status = $city->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return CityResource::make($city->fresh())
            ->response()
            ->setStatusCode($status);
    }

    /**
     * Update a city
     *
     * Any authenticated user may enrich a city: the OpenStreetMap reference, Wikidata,
     * Wikipedia and the coordinates. A city is reference data, not property — `created_by`
     * records who typed it in first, not who owns it.
     *
     * Five fields are the exception, because other records depend on them: `name`
     * (globally unique and carrying the frozen slug), `country_id`, `region_id`,
     * `population` and `population_date`. The last two decide, together with the boundary
     * data, whether this city's meetups appear in the BTC Map export — clearing one of them
     * removes entries from a third-party system. Changing any of the five requires being the
     * creator, a city steward or a super admin; a request that changes one without that
     * permission is rejected rather than silently ignored.
     *
     * Sending a field unchanged is never a change and never rejected.
     */
    #[ResponseAttribute(status: 403, description: 'Not authenticated, or an identity field (name, country_id, region_id, population, population_date) was changed without being the creator, a city steward or a super admin.')]
    #[ResponseAttribute(status: 422, description: 'Validation error.')]
    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $city->update($request->validated());

        return CityResource::make($city->fresh());
    }

    /**
     * List own cities
     *
     * Returns all cities created by the authenticated user, sorted alphabetically.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', City::class);

        $cities = City::query()
            ->where('created_by', $request->user()->id)
            ->orderBy('name')
            ->get();

        return CityResource::collection($cities);
    }

    /**
     * Show own city
     *
     * Shows a single city created by the authenticated user.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may view the city.')]
    public function mineShow(City $city): CityResource
    {
        Gate::authorize('view', $city);

        return CityResource::make($city);
    }
}
