<?php

namespace App\Mcp\Tools\CourseEvent;

use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Legt ein neues Kurs-Event für den authentifizierten Referenten an. Kurs und Veranstaltungsort werden über ihre Namen angegeben; der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateCourseEventTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! (bool) $user->is_lecturer) {
            return Response::error('Nur Referenten (is_lecturer) dürfen Kurs-Events anlegen.');
        }

        if (! $this->present($request->get('course_id'))) {
            $course = $this->resolveOwnedByName($request, Course::class, 'Kurse', 'course');

            if ($course instanceof Response) {
                return $course;
            }

            $request->merge(['course_id' => $course->id]);
        }

        if ($error = $this->mergeForeignKey($request, 'venue', 'venue_id', Venue::query(), 'Veranstaltungsorte')) {
            return $error;
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'link' => ['required', 'url', 'max:255'],
        ]);

        $courseEvent = CourseEvent::create($validated);

        return Response::json($courseEvent->fresh());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'course' => $schema->string()->description('Name deines Kurses, zu dem das Event gehört. Wird automatisch aufgelöst – sonst zuerst search-courses aufrufen.'),
            'course_id' => $schema->integer()->description('Optional: ID des Kurses, falls bereits bekannt (Alternative zu "course").'),
            'venue' => $schema->string()->description('Name des Veranstaltungsorts. Wird automatisch aufgelöst – bei Bedarf per search-venues den genauen Namen ermitteln.'),
            'venue_id' => $schema->integer()->description('Optional: ID des Veranstaltungsorts, falls bereits bekannt (Alternative zu "venue").'),
            'from' => $schema->string()->description('Startzeitpunkt (Datum/Uhrzeit).')->required(),
            'to' => $schema->string()->description('Endzeitpunkt (Datum/Uhrzeit), gleich oder nach dem Start.')->required(),
            'link' => $schema->string()->description('URL mit weiteren Informationen zum Kurs-Event.')->required(),
        ];
    }
}
