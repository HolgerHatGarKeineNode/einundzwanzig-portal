<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'Kurse', weight: 1)]
class CourseController extends Controller
{
    /**
     * Kurse auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id und name, alphabetisch sortiert. Ohne den Parameter
     * 'selected' wird das Ergebnis auf 10 Einträge begrenzt. Jeder Kurs enthält zusätzlich
     * ein 'image' (Logo-Thumbnail-URL).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen des Kurses.', required: false, type: 'string')]
    #[QueryParameter(name: 'user_id', description: 'Filtert die Kurse nach ihrem Ersteller.', required: false, type: 'integer')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen Kurs-IDs.', required: false, type: 'array')]
    public function index(Request $request)
    {
        return Course::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->when($request->has('user_id'),
                fn (Builder $query) => $query->where('created_by', $request->user_id))
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->where('name', 'ilike', "%{$request->search}%")
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id',
                    $request->input('selected', [])),
                fn (Builder $query) => $query->limit(10)
            )
            ->get()
            ->map(function (Course $course) {
                $course->image = $course->getFirstMediaUrl('logo',
                    'thumb');

                return $course;
            });
    }

    /**
     * Kurs anlegen
     *
     * Erlaubt einem authentifizierten Referenten, einen Kurs programmatisch anzulegen.
     */
    #[ResponseAttribute(status: 403, description: 'Nur Referenten (is_lecturer) dürfen Kurse anlegen.')]
    public function store(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()->is_lecturer, Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lecturer_id' => ['required', 'exists:lecturers,id'],
            'description' => ['nullable', 'string'],
        ]);

        $course = Course::create($validated);

        return response()->json($course->fresh(), Response::HTTP_CREATED);
    }

    #[ExcludeRouteFromDocs]
    public function show(Course $course)
    {
        //
    }

    /**
     * Kurs aktualisieren
     *
     * Aktualisiert einen Kurs; nur für den Ersteller oder einen Super-Admin.
     */
    #[ResponseAttribute(status: 403, description: 'Nur der Ersteller des Kurses oder ein Super-Admin darf ihn ändern.')]
    public function update(Request $request, Course $course): JsonResponse
    {
        abort_unless(
            (int) $course->created_by === $request->user()->id || $request->user()->hasRole('super-admin'),
            Response::HTTP_FORBIDDEN
        );

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'lecturer_id' => ['sometimes', 'required', 'exists:lecturers,id'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $course->update($validated);

        return response()->json($course->fresh());
    }

    #[ExcludeRouteFromDocs]
    public function destroy(Course $course)
    {
        //
    }
}
