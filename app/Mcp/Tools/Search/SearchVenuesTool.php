<?php

namespace App\Mcp\Tools\Search;

use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Sucht Veranstaltungsorte (öffentlich) und liefert id, name sowie die zugehörige Stadt/Land, alphabetisch sortiert, begrenzt auf 10 Einträge. Jeder Ort enthält zusätzlich flag (SVG-URL der Landesflagge) und description (Stadt + Straße). Dient zum Auflösen von venue_id.')]
class SearchVenuesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $search = $request->get('search');

        $venues = Venue::query()
            ->with(['city:id,name,country_id', 'city.country:id,name,code'])
            ->select('id', 'name', 'city_id')
            ->orderBy('name')
            ->when(
                $search,
                fn (Builder $query) => $query
                    ->where('name', 'ilike', "%{$search}%")
            )
            ->limit(10)
            ->get()
            ->map(function (Venue $venue) {
                $venue->flag = asset('vendor/blade-country-flags/4x3-'.$venue->city->country->code.'.svg');
                $venue->description = $venue->city->name.', '.$venue->street;

                return $venue;
            });

        return Response::json($venues->values());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Teilstring-Suche im Namen des Veranstaltungsortes.'),
        ];
    }
}
