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

#[Description('Aktualisiert ein bestehendes Kurs-Event. Nur der Ersteller oder ein Super-Admin darf es ändern.')]
class UpdateCourseEventTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $courseEvent = CourseEvent::find($request->get('id'));

        if (! $courseEvent) {
            return Response::error('Kurs-Event nicht gefunden.');
        }

        $user = $request->user();

        if (! $user instanceof User || ((int) $courseEvent->created_by !== $user->getAuthIdentifier() && ! $user->hasRole('super-admin'))) {
            return Response::error('Nur der Ersteller des Kurs-Events oder ein Super-Admin darf es ändern.');
        }

        if ($error = $this->mergeForeignKey($request, 'course', 'course_id', Course::query()->where('created_by', $user->getAuthIdentifier()), 'Kurse', false)) {
            return $error;
        }

        if ($error = $this->mergeForeignKey($request, 'venue', 'venue_id', Venue::query(), 'Veranstaltungsorte', false)) {
            return $error;
        }

        $validated = $request->validate([
            'course_id' => ['sometimes', 'required', 'integer', 'exists:courses,id'],
            'venue_id' => ['sometimes', 'required', 'integer', 'exists:venues,id'],
            'from' => ['sometimes', 'required', 'date'],
            'to' => ['sometimes', 'required', 'date', 'after_or_equal:from'],
            'link' => ['sometimes', 'required', 'url', 'max:255'],
        ]);

        $courseEvent->update($validated);

        return Response::json($courseEvent->fresh());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des zu aktualisierenden Kurs-Events (über list-my-course-events ermitteln; nicht den Nutzer danach fragen).')->required(),
            'course' => $schema->string()->description('Name des zugehörigen Kurses, falls geändert werden soll (wird automatisch aufgelöst).'),
            'course_id' => $schema->integer()->description('Optional: ID des Kurses (Alternative zu "course").'),
            'venue' => $schema->string()->description('Name des Veranstaltungsorts, falls geändert werden soll (wird automatisch aufgelöst).'),
            'venue_id' => $schema->integer()->description('Optional: ID des Veranstaltungsorts (Alternative zu "venue").'),
            'from' => $schema->string()->description('Startzeitpunkt (Datum/Uhrzeit).'),
            'to' => $schema->string()->description('Endzeitpunkt (Datum/Uhrzeit), gleich oder nach dem Start.'),
            'link' => $schema->string()->description('URL mit weiteren Informationen zum Kurs-Event.'),
        ];
    }
}
