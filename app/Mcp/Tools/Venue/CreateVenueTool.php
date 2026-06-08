<?php

namespace App\Mcp\Tools\Venue;

use App\Http\Requests\Api\StoreVenueRequest;
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

#[Description('Legt einen neuen Veranstaltungsort (Venue) für den authentifizierten Nutzer an. Die Stadt wird über ihren Namen angegeben; der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateVenueTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('create', Venue::class)) {
            return Response::error('Nicht berechtigt, einen Veranstaltungsort anzulegen.');
        }

        if ($error = $this->mergeForeignKey($request, 'city', 'city_id', City::query(), 'Stadt')) {
            return $error;
        }

        $storeRequest = new StoreVenueRequest;

        $validated = $request->validate(
            $storeRequest->rules(),
            $storeRequest->messages(),
        );

        $venue = Venue::create($validated);

        return Response::json(VenueResource::make($venue->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'city' => $schema->string()->description('Name der zugehörigen Stadt (z. B. "Ansbach"). Wird automatisch aufgelöst – bei Bedarf per search-cities den genauen Namen ermitteln.'),
            'city_id' => $schema->integer()->description('Optional: ID der Stadt, falls bereits bekannt (Alternative zu "city").'),
            'name' => $schema->string()->description('Name des Veranstaltungsorts.')->required(),
            'street' => $schema->string()->description('Straße und Hausnummer des Veranstaltungsorts.')->required(),
        ];
    }
}
