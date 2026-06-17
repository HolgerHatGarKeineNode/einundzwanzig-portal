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
     * Liefert die im Dashboard ausgewählten Meetups des authentifizierten Nutzers
     * (meetup_user-Pivot, „Meine Meetups"), alphabetisch sortiert.
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
     * Bestehendes Meetup zu „Meine Meetups“ hinzufügen
     *
     * Fügt ein bereits existierendes Meetup zur „Meine Meetups"-Liste des authentifizierten
     * Nutzers hinzu (meetup_user-Pivot als Mitglied, is_leader=false). Idempotent: ein bereits
     * hinzugefügtes Meetup bleibt unverändert. Die Stammdaten bleiben dem Ersteller vorbehalten.
     */
    #[Response(status: 401, description: 'Nicht authentifiziert.')]
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
     * Meetup aus „Meine Meetups“ entfernen
     *
     * Entfernt ein Meetup aus der „Meine Meetups"-Liste des authentifizierten Nutzers
     * (löst die meetup_user-Pivot-Mitgliedschaft). Die Stammdaten des Meetups bleiben
     * erhalten — Gegenstück zu addToMine(). Idempotent: war das Meetup nicht (mehr)
     * zugeordnet, bleibt die Antwort 200 OK.
     */
    #[Response(status: 401, description: 'Nicht authentifiziert.')]
    public function removeFromMine(Request $request, Meetup $meetup): MeetupResource
    {
        Gate::authorize('removeFromMine', $meetup);

        $meetup->removeMember($request->user());

        return MeetupResource::make($meetup);
    }

    /**
     * Eigenes Meetup anzeigen
     *
     * Zeigt ein einzelnes der im Dashboard ausgewählten Meetups (meetup_user-Pivot).
     */
    #[Response(status: 403, description: 'Nur der Ersteller oder ein Mitglied (meetup_user-Pivot) darf das Meetup sehen.')]
    public function mineShow(Meetup $meetup): MeetupResource
    {
        Gate::authorize('viewMine', $meetup);

        return MeetupResource::make($meetup);
    }

    /**
     * Meetup-Logo hochladen
     *
     * Lädt ein Logo (multipart, Feld „file") in die singleFile-Collection „logo" und ersetzt
     * dabei ein vorhandenes Logo. Nur für den Ersteller oder einen Super-Admin. Die Antwort
     * enthält die frische Logo-URL.
     */
    #[Response(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf das Logo ersetzen.')]
    #[Response(status: 422, description: 'Validierungsfehler (kein Bild, falscher MIME-Typ, zu groß oder zu große Abmessungen).')]
    public function uploadLogo(UploadMediaRequest $request, Meetup $meetup): MeetupResource
    {
        $meetup->addMedia($request->file('file')->getRealPath())
            ->usingName($meetup->name)
            ->toMediaCollection('logo');

        return MeetupResource::make($meetup->fresh());
    }
}
