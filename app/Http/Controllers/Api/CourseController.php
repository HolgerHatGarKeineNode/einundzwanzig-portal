<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
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
    use FiltersNumericIds;

    /**
     * Kurse auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id und name, alphabetisch sortiert. Ohne den Parameter
     * 'selected' wird das Ergebnis auf 10 Einträge begrenzt. Jeder Kurs enthält zusätzlich
     * ein 'image' (Logo-Thumbnail-URL). Mit 'withDetails' entfällt das Limit und jeder Kurs
     * enthält zusätzlich description, lecturer (id, name, subtitle, image) und next_event
     * (Beginn des nächsten zukünftigen Kurs-Events oder null).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen des Kurses.', required: false, type: 'string')]
    #[QueryParameter(name: 'user_id', description: 'Filtert die Kurse nach ihrem Ersteller.', required: false, type: 'integer')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen Kurs-IDs.', required: false, type: 'array')]
    #[QueryParameter(name: 'withDetails', description: 'Presence-Flag: liefert description, lecturer und next_event mit und hebt das 10-Einträge-Limit auf.', required: false, type: 'string')]
    public function index(Request $request)
    {
        $withDetails = $request->exists('withDetails');

        return Course::query()
            ->select($withDetails ? ['id', 'name', 'description', 'lecturer_id'] : ['id', 'name'])
            ->with('media')
            ->orderBy('name')
            ->when($withDetails, fn (Builder $query) => $query
                ->with('lecturer.media')
                ->withMin(['courseEvents as next_event' => fn (Builder $events) => $events->where('from', '>=', now())], 'from'))
            ->when($request->has('user_id'),
                fn (Builder $query) => $query->where('created_by', $request->integer('user_id')))
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
            ->map(function (Course $course) use ($withDetails) {
                $course->image = $course->getFirstMediaUrl('logo',
                    'thumb');

                if (! $withDetails) {
                    return $course;
                }

                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'image' => $course->image,
                    'description' => $course->description,
                    'next_event' => $course->next_event,
                    'lecturer' => $this->lecturerSummary($course->lecturer),
                ];
            });
    }

    /**
     * Referenten-Kurzinfo für Kurs-Antworten (Liste und Detail).
     *
     * @return array<string, mixed>|null
     */
    private function lecturerSummary(?Lecturer $lecturer): ?array
    {
        if ($lecturer === null) {
            return null;
        }

        return [
            'id' => $lecturer->id,
            'name' => $lecturer->name,
            'subtitle' => $lecturer->subtitle,
            'image' => $lecturer->getFirstMediaUrl('avatar', 'thumb'),
        ];
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

    /**
     * Kurs anzeigen
     *
     * Öffentlicher Endpunkt; liefert einen Kurs mit Beschreibung, Logo, Referent
     * und allen kommenden Kurs-Events (inkl. Veranstaltungsort und Stadt),
     * aufsteigend nach Beginn sortiert.
     *
     * @return array<string, mixed>
     */
    public function show(Course $course): array
    {
        $course->load([
            'lecturer.media',
            'media',
            'courseEvents' => fn ($query) => $query
                ->where('from', '>=', now())
                ->orderBy('from')
                ->with('venue.city.country'),
        ]);

        return [
            'id' => $course->id,
            'name' => $course->name,
            'description' => $course->description,
            'image' => $course->getFirstMediaUrl('logo', 'preview'),
            'portalLink' => url()->route('courses.landingpage', [
                'country' => config('app.domain_country'),
                'course' => $course,
            ]),
            'lecturer' => $this->lecturerSummary($course->lecturer),
            'events' => $course->courseEvents->map(fn (CourseEvent $event) => [
                'id' => $event->id,
                'course_id' => $event->course_id,
                'venue_id' => $event->venue_id,
                'from' => $event->from,
                'to' => $event->to,
                'link' => $event->link,
                'venue' => [
                    'id' => $event->venue->id,
                    'name' => $event->venue->name,
                    'city' => [
                        'id' => $event->venue->city->id,
                        'name' => $event->venue->city->name,
                        'country_id' => $event->venue->city->country_id,
                        'country' => [
                            'id' => $event->venue->city->country->id,
                            'name' => $event->venue->city->country->name,
                            'code' => $event->venue->city->country->code,
                        ],
                    ],
                ],
            ])->all(),
        ];
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
