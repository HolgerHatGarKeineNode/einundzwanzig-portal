<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCourseEventRequest;
use App\Http\Requests\Api\UpdateCourseEventRequest;
use App\Http\Resources\CourseEventResource;
use App\Models\CourseEvent;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'Kurs-Events', weight: 2)]
class CourseEventController extends Controller
{
    /**
     * Eigene Kurs-Events auflisten
     *
     * Liefert alle vom authentifizierten Nutzer erstellten Kurs-Events (inkl. zugehörigem
     * Kurs und Veranstaltungsort), absteigend nach Startdatum. Ideal für idempotente
     * Synchronisierung durch externe Clients.
     */
    #[QueryParameter(name: 'course_id', description: 'Filtert die Kurs-Events auf einen bestimmten Kurs.', required: false, type: 'integer')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $courseEvents = CourseEvent::query()
            ->with(['course:id,name', 'venue:id,name'])
            ->where('created_by', $request->user()->id)
            ->when(
                $request->filled('course_id'),
                fn (Builder $query) => $query->where('course_id', $request->integer('course_id'))
            )
            ->orderByDesc('from')
            ->get();

        return CourseEventResource::collection($courseEvents);
    }

    /**
     * Kurs-Event anlegen
     *
     * Erlaubt einem authentifizierten Referenten, ein datiertes Kurs-Event programmatisch anzulegen.
     */
    #[ResponseAttribute(status: 403, description: 'Nur Referenten (is_lecturer) dürfen Kurs-Events anlegen.')]
    public function store(StoreCourseEventRequest $request): JsonResponse
    {
        $courseEvent = CourseEvent::create($request->validated());

        return CourseEventResource::make($courseEvent->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Kurs-Event aktualisieren
     *
     * Aktualisiert ein Kurs-Event; nur für den Ersteller oder einen Super-Admin.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller des Kurs-Events oder ein Super-Admin darf es ändern.')]
    public function update(UpdateCourseEventRequest $request, CourseEvent $courseEvent): CourseEventResource
    {
        $courseEvent->update($request->validated());

        return CourseEventResource::make($courseEvent->fresh());
    }
}
