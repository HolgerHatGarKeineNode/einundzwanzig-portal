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

#[Description('Aktualisiert einen deiner Kurse (per Name angegeben). Nur der Ersteller oder ein Super-Admin darf ihn ändern.')]
class UpdateCourseTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $course = $this->resolveOwnedByName($request, Course::class, 'Kurse', 'course');

        if ($course instanceof Response) {
            return $course;
        }

        $user = $request->user();

        if (! $user instanceof User || ((int) $course->created_by !== $user->getAuthIdentifier() && ! $user->hasRole('super-admin'))) {
            return Response::error('Nur der Ersteller des Kurses oder ein Super-Admin darf ihn ändern.');
        }

        if ($error = $this->mergeForeignKey($request, 'lecturer', 'lecturer_id', Lecturer::query(), 'Referenten', false)) {
            return $error;
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
            'course' => $schema->string()->description('Name des zu ändernden Kurses (aus deinen Kursen, siehe list-my-course-events bzw. search-courses).'),
            'id' => $schema->integer()->description('Optional: ID des Kurses, falls bereits bekannt (Alternative zu "course").'),
            'name' => $schema->string()->description('Neuer Name des Kurses.'),
            'lecturer' => $schema->string()->description('Name des zugehörigen Referenten (wird automatisch aufgelöst).'),
            'lecturer_id' => $schema->integer()->description('Optional: ID des Referenten (Alternative zu "lecturer").'),
            'description' => $schema->string()->description('Beschreibung des Kurses.'),
        ];
    }
}
