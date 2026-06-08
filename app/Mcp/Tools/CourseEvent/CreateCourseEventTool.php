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

#[Description('Legt ein neues Kurs-Event für den authentifizierten Referenten an. Der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateCourseEventTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! (bool) $user->is_lecturer) {
            return Response::error('Nur Referenten (is_lecturer) dürfen Kurs-Events anlegen.');
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
            'course_id' => $schema->integer()->description('ID des zugehörigen Kurses (vorher per search-courses auflösen).')->required(),
            'venue_id' => $schema->integer()->description('ID des Veranstaltungsorts (vorher per search-venues auflösen).')->required(),
            'from' => $schema->string()->description('Startzeitpunkt (Datum/Uhrzeit).')->required(),
            'to' => $schema->string()->description('Endzeitpunkt (Datum/Uhrzeit), gleich oder nach dem Start.')->required(),
            'link' => $schema->string()->description('URL mit weiteren Informationen zum Kurs-Event.')->required(),
        ];
    }
}
