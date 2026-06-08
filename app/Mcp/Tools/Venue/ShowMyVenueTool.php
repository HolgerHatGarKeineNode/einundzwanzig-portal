<?php

namespace App\Mcp\Tools\Venue;

use App\Http\Resources\VenueResource;
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
#[Description('Zeigt einen einzelnen, vom authentifizierten Nutzer erstellten Veranstaltungsort.')]
class ShowMyVenueTool extends Tool
{
    public function handle(Request $request): Response
    {
        $venue = Venue::find($request->get('id'));

        if (! $venue) {
            return Response::error('Veranstaltungsort nicht gefunden.');
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
            'id' => $schema->integer()->description('ID des Veranstaltungsorts.')->required(),
        ];
    }
}
