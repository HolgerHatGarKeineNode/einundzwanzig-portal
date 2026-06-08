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

#[Description('Aktualisiert einen bestehenden Kurs. Nur der Ersteller oder ein Super-Admin darf ihn ändern.')]
class UpdateCourseTool extends Tool
{
    public function handle(Request $request): Response
    {
        $course = Course::find($request->get('id'));

        if (! $course) {
            return Response::error('Kurs nicht gefunden.');
        }

        $user = $request->user();

        if (! $user instanceof User || ((int) $course->created_by !== $user->getAuthIdentifier() && ! $user->hasRole('super-admin'))) {
            return Response::error('Nur der Ersteller des Kurses oder ein Super-Admin darf ihn ändern.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'lecturer_id' => ['sometimes', 'required', 'exists:lecturers,id'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $course->update($validated);

        return Response::json($course->fresh());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des zu aktualisierenden Kurses.')->required(),
            'name' => $schema->string()->description('Name des Kurses.'),
            'lecturer_id' => $schema->integer()->description('ID des zugehörigen Referenten.'),
            'description' => $schema->string()->description('Beschreibung des Kurses.'),
        ];
    }
}
