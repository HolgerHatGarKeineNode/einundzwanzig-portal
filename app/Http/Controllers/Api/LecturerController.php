<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLecturerRequest;
use App\Http\Requests\Api\UpdateLecturerRequest;
use App\Http\Resources\LecturerResource;
use App\Models\Lecturer;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'Referenten', weight: 4)]
class LecturerController extends Controller
{
    use FiltersNumericIds;

    /**
     * Referenten auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id und name, alphabetisch sortiert. Ohne den Parameter 'selected' wird die Liste auf 10 Einträge begrenzt. Jeder Referent enthält zusätzlich ein 'image' (Avatar-Thumbnail-URL).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen IDs.', required: false, type: 'array')]
    public function index(Request $request)
    {
        return Lecturer::query()
            ->select('id', 'name')
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
            ->get()
            ->map(function (Lecturer $lecturer) {
                $lecturer->image = $lecturer->getFirstMediaUrl('avatar',
                    'thumb');

                return $lecturer;
            });
    }

    /**
     * Referent anlegen
     *
     * Erlaubt einem authentifizierten Nutzer, einen Referenten programmatisch anzulegen.
     * Der Ersteller (created_by) wird automatisch gesetzt.
     */
    #[ResponseAttribute(status: 401, description: 'Nicht authentifiziert.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function store(StoreLecturerRequest $request): JsonResponse
    {
        $lecturer = Lecturer::create($request->validated());

        return LecturerResource::make($lecturer->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Referent aktualisieren
     *
     * Aktualisiert einen Referenten; nur fuer den Ersteller oder einen Super-Admin.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf den Referenten aendern.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler.')]
    public function update(UpdateLecturerRequest $request, Lecturer $lecturer): LecturerResource
    {
        $lecturer->update($request->validated());

        return LecturerResource::make($lecturer->fresh());
    }

    /**
     * Eigene Referenten auflisten
     *
     * Liefert alle vom authentifizierten Nutzer erstellten Referenten, alphabetisch sortiert.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lecturer::class);

        $lecturers = Lecturer::query()
            ->where('created_by', $request->user()->id)
            ->orderBy('name')
            ->get();

        return LecturerResource::collection($lecturers);
    }

    /**
     * Eigenen Referenten anzeigen
     *
     * Zeigt einen einzelnen, vom authentifizierten Nutzer erstellten Referenten.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf den Referenten sehen.')]
    public function mineShow(Lecturer $lecturer): LecturerResource
    {
        Gate::authorize('view', $lecturer);

        return LecturerResource::make($lecturer);
    }
}
