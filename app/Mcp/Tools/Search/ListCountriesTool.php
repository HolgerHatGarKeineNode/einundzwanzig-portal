<?php

namespace App\Mcp\Tools\Search;

use App\Models\Country;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Listet Länder (öffentlich) und liefert id, name und code (Ländercode), alphabetisch sortiert, begrenzt auf 10 Einträge. Jedes Land enthält zusätzlich eine flag (SVG-URL). Dient zum Auflösen von country_id.')]
class ListCountriesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $search = $request->get('search');

        $countries = Country::query()
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->when(
                $search,
                fn (Builder $query) => $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%"),
            )
            ->limit(10)
            ->get()
            ->map(function (Country $country) {
                $country->flag = asset('vendor/blade-country-flags/4x3-'.$country->code.'.svg');

                return $country;
            });

        return Response::json($countries->values());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Suche in Name oder Code (Ländercode).'),
        ];
    }
}
