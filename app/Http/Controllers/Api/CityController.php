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

#[Group(name: 'Stammdaten', weight: 5)]
class CityController extends Controller
{
    use FiltersNumericIds;

    /**
     * Städte auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id, name und das zugehörige Land, alphabetisch sortiert. Ohne 'selected' wird die Liste auf 10 Einträge begrenzt.
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen der Stadt.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen IDs.', required: false, type: 'array')]
    public function index(Request $request)
    {
        return City::query()
            ->with(['country:id,name'])
            ->select('id', 'name', 'country_id')
            ->orderBy('name')
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->where('name', 'ilike', "%{$request->search}%")
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id', $this->numericIds($request)),
                fn (Builder $query) => $query->limit(10)
            )
            ->get();
    }

    /**
     * Stadt anlegen
     *
     * Erlaubt einem authentifizierten Nutzer, eine Stadt programmatisch anzulegen.
     * Der Ersteller (created_by) wird automatisch gesetzt.
     */
    #[ResponseAttribute(status: 401, description: 'Nicht authentifiziert.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = City::create($request->validated());

        return CityResource::make($city->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Stadt aktualisieren
     *
     * Aktualisiert eine Stadt; nur fuer den Ersteller oder einen Super-Admin.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf die Stadt aendern.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $city->update($request->validated());

        return CityResource::make($city->fresh());
    }

    /**
     * Eigene Staedte auflisten
     *
     * Liefert alle vom authentifizierten Nutzer erstellten Staedte, alphabetisch sortiert.
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
     * Eigene Stadt anzeigen
     *
     * Zeigt eine einzelne, vom authentifizierten Nutzer erstellte Stadt.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf die Stadt sehen.')]
    public function mineShow(City $city): CityResource
    {
        Gate::authorize('view', $city);

        return CityResource::make($city);
    }
}
