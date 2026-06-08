<?php

namespace App\Mcp\Tools\Search;

use App\Models\Lecturer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Sucht Referenten (öffentlich) und liefert id und name, alphabetisch sortiert, begrenzt auf 10 Einträge. Jeder Referent enthält zusätzlich ein image (Avatar-Thumbnail-URL). Dient zum Auflösen von lecturer_id.')]
class SearchLecturersTool extends Tool
{
    public function handle(Request $request): Response
    {
        $search = $request->get('search');

        $lecturers = Lecturer::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->when(
                $search,
                fn (Builder $query) => $query
                    ->where('name', 'ilike', "%{$search}%")
            )
            ->limit(10)
            ->get()
            ->map(function (Lecturer $lecturer) {
                $lecturer->image = $lecturer->getFirstMediaUrl('avatar',
                    'thumb');

                return $lecturer;
            });

        return Response::json($lecturers->values());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Teilstring-Suche im Namen.'),
        ];
    }
}
