<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMeetupRequest;
use App\Http\Requests\Api\UpdateMeetupRequest;
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
     * Eigene Meetups auflisten
     *
     * Liefert die Meetups des angemeldeten Nutzers (id, name, inklusive Stadt/Land und Profilbild),
     * alphabetisch sortiert. Erfordert eine authentifizierte Sitzung (sonst 401).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Meetup- oder Stadtnamen.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen Meetup-IDs.', required: false, type: 'array')]
    #[Response(status: 401, description: 'Nicht authentifiziert.')]
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
                    ->where('name', 'like', "%{$request->search}%")
                    ->orWhereHas('city',
                        fn (Builder $query) => $query->where('cities.name', 'ilike', "%{$request->search}%")),
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
     * Meetup anlegen
     *
     * Erlaubt einem authentifizierten Nutzer, ein Meetup programmatisch anzulegen.
     * Der Ersteller (created_by) wird automatisch gesetzt.
     */
    #[Response(status: 401, description: 'Nicht authentifiziert.')]
    #[Response(status: 422, description: 'Validierungsfehler.')]
    public function store(StoreMeetupRequest $request): JsonResponse
    {
        $meetup = Meetup::create($request->validated());

        return MeetupResource::make($meetup->fresh())
            ->response()
            ->setStatusCode(\Symfony\Component\HttpFoundation\Response::HTTP_CREATED);
    }

    /**
     * Meetup aktualisieren
     *
     * Aktualisiert ein Meetup; nur fuer den Ersteller oder einen Super-Admin.
     */
    #[Response(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf das Meetup aendern.')]
    #[Response(status: 422, description: 'Validierungsfehler.')]
    public function update(UpdateMeetupRequest $request, Meetup $meetup): MeetupResource
    {
        $meetup->update($request->validated());

        return MeetupResource::make($meetup->fresh());
    }

    /**
     * Eigene Meetups auflisten
     *
     * Liefert alle vom authentifizierten Nutzer erstellten Meetups, alphabetisch sortiert.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Meetup::class);

        $meetups = Meetup::query()
            ->where('created_by', $request->user()->id)
            ->orderBy('name')
            ->get();

        return MeetupResource::collection($meetups);
    }

    /**
     * Eigenes Meetup anzeigen
     *
     * Zeigt ein einzelnes, vom authentifizierten Nutzer erstelltes Meetup.
     */
    #[Response(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf das Meetup sehen.')]
    public function mineShow(Meetup $meetup): MeetupResource
    {
        Gate::authorize('view', $meetup);

        return MeetupResource::make($meetup);
    }
}
