<?php

namespace App\Mcp\Tools\Venue;

use App\Http\Resources\VenueResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Zeigt einen deiner Veranstaltungsorte (per Name angegeben).')]
class ShowMyVenueTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $venue = $this->resolveOwnedByName($request, Venue::class, 'Veranstaltungsorte', 'venue');

        if ($venue instanceof Response) {
            return $venue;
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('view', $venue)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diesen Veranstaltungsort sehen.');
        }

        return Response::json(VenueResource::make($venue)->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'venue' => $schema->string()->description('Name des Veranstaltungsorts (aus deinen Orten, siehe list-my-venues).'),
            'id' => $schema->integer()->description('Optional: ID des Veranstaltungsorts, falls bereits bekannt (Alternative zu "venue").'),
        ];
    }
}
