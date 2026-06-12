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

#[Group(name: 'Stammdaten', weight: 5)]
class VenueController extends Controller
{
    use FiltersNumericIds;

    /**
     * Veranstaltungsorte auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id, name und die zugehörige Stadt/Land, alphabetisch sortiert.
     * Ohne 'selected' wird die Liste auf 10 Einträge begrenzt. Jeder Ort enthält zusätzlich
     * 'flag' (SVG-URL der Landesflagge) und 'description' (Stadt + Straße).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen des Veranstaltungsortes.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen Veranstaltungsort-IDs (umgeht die Begrenzung auf 10 Einträge).', required: false, type: 'array')]
    #[QueryParameter(name: 'withDetails', description: 'Presence-Flag: hebt die Begrenzung auf 10 Einträge auf.', required: false, type: 'string')]
    public function index(Request $request)
    {
        return Venue::query()
            ->with(['city:id,name,country_id', 'city.country:id,name,code'])
            ->select('id', 'name', 'city_id')
            ->orderBy('name')
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->where('name', 'ilike', "%{$request->search}%")
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id', $this->numericIds($request)),
                fn (Builder $query) => $request->exists('withDetails') ? $query : $query->limit(10)
            )
            ->get()
            ->map(function (Venue $venue) {
                $venue->flag = asset('vendor/blade-country-flags/4x3-'.$venue->city->country->code.'.svg');
                $venue->description = $venue->city->name.', '.$venue->street;

                return $venue;
            });
    }

    /**
     * Veranstaltungsort anlegen
     *
     * Erlaubt einem authentifizierten Nutzer, einen Veranstaltungsort programmatisch anzulegen.
     * Der Ersteller (created_by) wird automatisch gesetzt.
     */
    #[ResponseAttribute(status: 401, description: 'Nicht authentifiziert.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function store(StoreVenueRequest $request): JsonResponse
    {
        $venue = Venue::create($request->validated());

        return VenueResource::make($venue->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Veranstaltungsort aktualisieren
     *
     * Aktualisiert einen Veranstaltungsort; nur fuer den Ersteller oder einen Super-Admin.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf den Veranstaltungsort aendern.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function update(UpdateVenueRequest $request, Venue $venue): VenueResource
    {
        $venue->update($request->validated());

        return VenueResource::make($venue->fresh());
    }

    /**
     * Eigene Veranstaltungsorte auflisten
     *
     * Liefert alle vom authentifizierten Nutzer erstellten Veranstaltungsorte, alphabetisch sortiert.
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
     * Eigenen Veranstaltungsort anzeigen
     *
     * Zeigt einen einzelnen, vom authentifizierten Nutzer erstellten Veranstaltungsort.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf den Veranstaltungsort sehen.')]
    public function mineShow(Venue $venue): VenueResource
    {
        Gate::authorize('view', $venue);

        return VenueResource::make($venue);
    }
}
