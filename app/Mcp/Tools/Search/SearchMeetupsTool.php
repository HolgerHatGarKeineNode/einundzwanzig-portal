<?php

namespace App\Mcp\Tools\Search;

use App\Models\Meetup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Sucht bestehende Meetups (öffentlich) nach Meetup-Name ODER Stadtname und liefert id, name, Stadt und Land, begrenzt auf 10 Einträge. VOR dem Anlegen eines neuen Meetups nutzen, um Duplikate zu vermeiden.')]
class SearchMeetupsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $search = $request->get('search');

        $meetups = Meetup::query()
            ->select('id', 'name', 'city_id', 'slug')
            ->with(['city:id,name,country_id', 'city.country:id,name'])
            ->orderBy('name')
            ->when(
                is_string($search) && $search !== '',
                function (Builder $query) use ($search): void {
                    $needle = '%'.mb_strtolower(trim((string) $search)).'%';

                    $query->where(function (Builder $inner) use ($needle): void {
                        $inner->whereRaw('LOWER(name) LIKE ?', [$needle])
                            ->orWhereHas('city', fn (Builder $city) => $city->whereRaw('LOWER(cities.name) LIKE ?', [$needle]));
                    });
                }
            )
            ->limit(10)
            ->get()
            ->map(fn (Meetup $meetup): array => [
                'id' => $meetup->id,
                'name' => $meetup->name,
                'city' => $meetup->city?->name,
                'country' => $meetup->city?->country?->name,
            ]);

        return Response::json($meetups->values());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Suchbegriff: Meetup-Name oder Stadtname (z. B. "München").'),
        ];
    }
}
