<?php

namespace App\Mcp\Tools\Course;

use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Legt einen neuen Kurs für den authentifizierten Referenten an. Der Referent wird über seinen Namen angegeben; der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateCourseTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! (bool) $user->is_lecturer) {
            return Response::error('Nur Referenten (is_lecturer) dürfen Kurse anlegen.');
        }

        if ($error = $this->mergeForeignKey($request, 'lecturer', 'lecturer_id', Lecturer::query(), 'Referenten')) {
            return $error;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lecturer_id' => ['required', 'exists:lecturers,id'],
            'description' => ['nullable', 'string'],
        ]);

        $course = Course::create($validated);

        return Response::json($course->fresh());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name des Kurses.')->required(),
            'lecturer' => $schema->string()->description('Name des zugehörigen Referenten. Wird automatisch aufgelöst – bei Bedarf per search-lecturers den genauen Namen ermitteln.'),
            'lecturer_id' => $schema->integer()->description('Optional: ID des Referenten, falls bereits bekannt (Alternative zu "lecturer").'),
            'description' => $schema->string()->description('Beschreibung des Kurses.'),
        ];
    }
}
