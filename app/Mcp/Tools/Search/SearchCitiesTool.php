<?php

namespace App\Mcp\Tools\Search;

use App\Models\City;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Sucht Städte (öffentlich) und liefert id, name und das zugehörige Land, alphabetisch sortiert, begrenzt auf 10 Einträge. Dient zum Auflösen von city_id vor dem Anlegen von Datensätzen.')]
class SearchCitiesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $search = $request->get('search');

        $cities = City::query()
            ->with(['country:id,name'])
            ->select('id', 'name', 'country_id')
            ->orderBy('name')
            ->when(
                $search,
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%")
            )
            ->limit(10)
            ->get();

        return Response::json($cities->values());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Teilstring-Suche im Namen der Stadt.'),
        ];
    }
}
