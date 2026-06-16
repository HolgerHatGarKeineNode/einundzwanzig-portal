<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLecturerRequest;
use App\Http\Requests\Api\UpdateLecturerRequest;
use App\Http\Requests\Api\UploadMediaRequest;
use App\Http\Resources\LecturerResource;
use App\Models\Course;
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
     * Öffentlicher Endpunkt; liefert id und name, alphabetisch sortiert. Ohne den Parameter 'selected' wird die Liste auf 10 Einträge begrenzt. Jeder Referent enthält zusätzlich ein 'image' (Avatar-Thumbnail-URL). Mit 'withDetails' entfällt das Limit und jeder Referent enthält zusätzlich subtitle, future_events_count (Anzahl kommender Kurs-Events) und next_event (Datum des nächsten kommenden Kurs-Events, oder null).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen IDs.', required: false, type: 'array')]
    #[QueryParameter(name: 'withDetails', description: 'Presence-Flag: liefert subtitle, future_events_count und next_event mit und hebt das 10-Einträge-Limit auf.', required: false, type: 'string')]
    public function index(Request $request)
    {
        $withDetails = $request->exists('withDetails');

        return Lecturer::query()
            ->select($withDetails ? ['id', 'name', 'subtitle'] : ['id', 'name'])
            ->with('media')
            ->orderBy('name')
            ->when($withDetails, fn (Builder $query) => $query
                ->withCount(['coursesEvents as future_events_count' => fn (Builder $events) => $events->where('from', '>=', now())])
                ->withMin(['coursesEvents as next_event' => fn (Builder $events) => $events->where('from', '>=', now())], 'from'))
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
            ->map(function (Lecturer $lecturer) {
                $lecturer->image = $lecturer->getFirstMediaUrl('avatar',
                    'thumb');

                return $lecturer;
            });
    }

    /**
     * Referent anzeigen
     *
     * Öffentlicher Endpunkt; liefert das Profil eines Referenten (Avatar, Untertitel,
     * Intro, Beschreibung, Nostr- und Web-Links) inklusive seiner Kurse mit deren
     * nächstem zukünftigen Kurs-Event.
     *
     * @return array<string, mixed>
     */
    public function show(Lecturer $lecturer): array
    {
        $lecturer->load([
            'media',
            'courses' => fn ($query) => $query
                ->orderBy('name')
                ->with('media')
                ->withMin(['courseEvents as next_event' => fn (Builder $events) => $events->where('from', '>=', now())], 'from'),
        ]);

        return [
            'id' => $lecturer->id,
            'name' => $lecturer->name,
            'subtitle' => $lecturer->subtitle,
            'intro' => $lecturer->intro,
            'description' => $lecturer->description,
            'image' => $lecturer->getFirstMediaUrl('avatar', 'preview'),
            'active' => (bool) $lecturer->active,
            'nostr' => $lecturer->nostr,
            'website' => $lecturer->website,
            'twitter_username' => $lecturer->twitter_username,
            'lightning_address' => $lecturer->lightning_address,
            'courses' => $lecturer->courses->map(fn (Course $course) => [
                'id' => $course->id,
                'name' => $course->name,
                'image' => $course->getFirstMediaUrl('logo', 'thumb'),
                'next_event' => $course->next_event,
            ])->all(),
        ];
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
            ->with('media')
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

    /**
     * Referenten-Avatar hochladen
     *
     * Lädt einen Avatar (multipart, Feld „file") in die singleFile-Collection „avatar" und
     * ersetzt dabei ein vorhandenes Bild. Nur für den Ersteller oder einen Super-Admin. Die
     * Antwort enthält die frische Avatar-URL.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller oder ein Super-Admin darf den Avatar ersetzen.')]
    #[ResponseAttribute(status: 422, description: 'Validierungsfehler (kein Bild, falscher MIME-Typ, zu groß oder zu große Abmessungen).')]
    public function uploadAvatar(UploadMediaRequest $request, Lecturer $lecturer): LecturerResource
    {
        $lecturer->addMedia($request->file('file')->getRealPath())
            ->usingName($lecturer->name)
            ->toMediaCollection('avatar');

        return LecturerResource::make($lecturer->fresh());
    }
}
