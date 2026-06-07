<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseEvent;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     *
     * @return Collection<int, CourseEvent>
     */
    #[QueryParameter(name: 'course_id', description: 'Filtert die Kurs-Events auf einen bestimmten Kurs.', required: false, type: 'integer')]
    public function index(Request $request): Collection
    {
        return CourseEvent::query()
            ->with(['course:id,name', 'venue:id,name'])
            ->where('created_by', $request->user()->id)
            ->when(
                $request->filled('course_id'),
                fn (Builder $query) => $query->where('course_id', $request->integer('course_id'))
            )
            ->orderByDesc('from')
            ->get();
    }

    /**
     * Kurs-Event anlegen
     *
     * Erlaubt einem authentifizierten Referenten, ein datiertes Kurs-Event programmatisch anzulegen.
     */
    #[ResponseAttribute(status: 403, description: 'Nur Referenten (is_lecturer) dürfen Kurs-Events anlegen.')]
    public function store(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()->is_lecturer, Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'link' => ['required', 'url', 'max:255'],
        ]);

        $courseEvent = CourseEvent::create($validated);

        return response()->json($courseEvent->fresh(), Response::HTTP_CREATED);
    }

    /**
     * Kurs-Event aktualisieren
     *
     * Aktualisiert ein Kurs-Event; nur für den Ersteller oder einen Super-Admin.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller des Kurs-Events oder ein Super-Admin darf es ändern.')]
    public function update(Request $request, CourseEvent $courseEvent): JsonResponse
    {
        abort_unless(
            (int) $courseEvent->created_by === $request->user()->id || $request->user()->hasRole('super-admin'),
            Response::HTTP_FORBIDDEN
        );

        $validated = $request->validate([
            'course_id' => ['sometimes', 'required', 'integer', 'exists:courses,id'],
            'venue_id' => ['sometimes', 'required', 'integer', 'exists:venues,id'],
            'from' => ['sometimes', 'required', 'date'],
            'to' => ['sometimes', 'required', 'date', 'after_or_equal:from'],
            'link' => ['sometimes', 'required', 'url', 'max:255'],
        ]);

        $courseEvent->update($validated);

        return response()->json($courseEvent->fresh());
    }
}
