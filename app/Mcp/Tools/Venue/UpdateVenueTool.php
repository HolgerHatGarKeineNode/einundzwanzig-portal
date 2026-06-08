<?php

namespace App\Mcp\Tools\Venue;

use App\Http\Requests\Api\UpdateVenueRequest;
use App\Http\Resources\VenueResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\City;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert einen deiner Veranstaltungsorte (per Name angegeben). Nur der Ersteller oder ein Super-Admin darf ihn ändern.')]
class UpdateVenueTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $venue = $this->resolveOwnedByName($request, Venue::class, 'Veranstaltungsorte', 'venue');

        if ($venue instanceof Response) {
            return $venue;
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $venue)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diesen Veranstaltungsort ändern.');
        }

        if ($error = $this->mergeForeignKey($request, 'city', 'city_id', City::query(), 'Stadt', false)) {
            return $error;
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
            'venue' => $schema->string()->description('Name des zu ändernden Veranstaltungsorts (aus deinen Orten, siehe list-my-venues).'),
            'id' => $schema->integer()->description('Optional: ID des Veranstaltungsorts, falls bereits bekannt (Alternative zu "venue").'),
            'city' => $schema->string()->description('Name der zugehörigen Stadt (wird automatisch aufgelöst).'),
            'city_id' => $schema->integer()->description('Optional: ID der Stadt (Alternative zu "city").'),
            'name' => $schema->string()->description('Neuer Name des Veranstaltungsorts.'),
            'street' => $schema->string()->description('Straße und Hausnummer des Veranstaltungsorts.'),
        ];
    }
}
