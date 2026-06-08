<?php

namespace App\Mcp\Tools\CourseEvent;

use App\Models\CourseEvent;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert ein bestehendes Kurs-Event. Nur der Ersteller oder ein Super-Admin darf es ändern.')]
class UpdateCourseEventTool extends Tool
{
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
            'id' => $schema->integer()->description('ID des zu aktualisierenden Kurs-Events.')->required(),
            'course_id' => $schema->integer()->description('ID des zugehörigen Kurses.'),
            'venue_id' => $schema->integer()->description('ID des Veranstaltungsorts.'),
            'from' => $schema->string()->description('Startzeitpunkt (Datum/Uhrzeit).'),
            'to' => $schema->string()->description('Endzeitpunkt (Datum/Uhrzeit), gleich oder nach dem Start.'),
            'link' => $schema->string()->description('URL mit weiteren Informationen zum Kurs-Event.'),
        ];
    }
}
