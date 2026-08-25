<?php

namespace App\Mcp\Tools\Course;

use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\Course;
use App\Models\Lecturer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
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

        /*
         * Die Berechtigung beantwortet die Policy, nicht dieses Tool.
         *
         * Hier stand `! (bool) $user->is_lecturer`. Seit P1 ist genau diese Bedingung aus
         * `CoursePolicy::create()` entfernt — sie war nie ein Gate, weil beide
         * Anlagepfade das Flag bei jeder Registrierung setzen. Die Inline-Kopie hier
         * blieb aber stehen und sperrte damit aus, was die REST-API (ueber
         * `StoreCourseRequest::authorize()`) inzwischen erlaubt: MCP und REST sagten
         * verschiedene Dinge ueber dieselbe Frage.
         *
         * Dass die Ability heute `true` liefert, macht den Aufruf nicht ueberfluessig —
         * er sorgt dafuer, dass eine spaetere Aenderung an der Policy hier ankommt,
         * statt hier vergessen zu werden. Was schuetzt, ist `update()`.
         */
        if ($user === null || Gate::forUser($user)->denies('create', Course::class)) {
            return Response::error('Nicht berechtigt, einen Kurs anzulegen.');
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
