<?php

namespace App\Mcp\Tools\CourseEvent;

use App\Models\CourseEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Listet alle vom authentifizierten Nutzer erstellten Kurs-Events (inkl. Kurs und Stadt), absteigend nach Startdatum. Optional nach Kurs filterbar.')]
class ListMyCourseEventsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        $events = CourseEvent::query()
            ->with(['course:id,name', 'city:id,name'])
            ->where('created_by', $user->getAuthIdentifier())
            ->when(
                $request->filled('course_id'),
                fn ($query) => $query->where('course_id', $request->integer('course_id'))
            )
            ->orderByDesc('from')
            ->get();

        return Response::json($events);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'course_id' => $schema->integer()->description('Filtert die Kurs-Events auf einen bestimmten Kurs.'),
        ];
    }
}
