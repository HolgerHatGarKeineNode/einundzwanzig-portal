<?php

namespace App\Mcp\Tools\Search;

use App\Models\Course;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Sucht Kurse (öffentlich) und liefert id und name, alphabetisch sortiert, begrenzt auf 10 Einträge. Jeder Kurs enthält zusätzlich ein image (Logo-Thumbnail-URL). Optional nach Ersteller (user_id) filterbar. Dient zum Auflösen von course_id.')]
class SearchCoursesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $search = $request->get('search');
        $userId = $request->get('user_id');

        $courses = Course::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->when($userId !== null,
                fn (Builder $query) => $query->where('created_by', (int) $userId))
            ->when(
                $search,
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%")
            )
            ->limit(10)
            ->get()
            ->map(function (Course $course) {
                $course->image = $course->getFirstMediaUrl('logo',
                    'thumb');

                return $course;
            });

        return Response::json($courses->values());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Teilstring-Suche im Namen des Kurses.'),
            'user_id' => $schema->integer()->description('Filtert die Kurse nach ihrem Ersteller.'),
        ];
    }
}
