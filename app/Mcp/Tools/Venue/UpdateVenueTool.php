<?php

namespace App\Mcp\Tools\Venue;

use App\Http\Requests\Api\UpdateVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert einen bestehenden Veranstaltungsort (Venue). Nur der Ersteller oder ein Super-Admin darf ihn ändern.')]
class UpdateVenueTool extends Tool
{
    public function handle(Request $request): Response
    {
        $venue = Venue::find($request->get('id'));

        if (! $venue) {
            return Response::error('Veranstaltungsort nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $venue)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diesen Veranstaltungsort ändern.');
        }

        $validated = $request->validate((new UpdateVenueRequest)->rules());

        $venue->update($validated);

        return Response::json(VenueResource::make($venue->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des zu aktualisierenden Veranstaltungsorts.')->required(),
            'city_id' => $schema->integer()->description('ID der zugehörigen Stadt.'),
            'name' => $schema->string()->description('Name des Veranstaltungsorts.'),
            'street' => $schema->string()->description('Straße und Hausnummer des Veranstaltungsorts.'),
        ];
    }
}
