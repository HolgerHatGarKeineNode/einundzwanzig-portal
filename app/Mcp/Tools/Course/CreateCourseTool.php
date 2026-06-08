<?php

namespace App\Mcp\Tools\Course;

use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Legt einen neuen Kurs für den authentifizierten Referenten an. Der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateCourseTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! (bool) $user->is_lecturer) {
            return Response::error('Nur Referenten (is_lecturer) dürfen Kurse anlegen.');
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
            'lecturer_id' => $schema->integer()->description('ID des zugehörigen Referenten (vorher per search-lecturers auflösen).')->required(),
            'description' => $schema->string()->description('Beschreibung des Kurses.'),
        ];
    }
}
