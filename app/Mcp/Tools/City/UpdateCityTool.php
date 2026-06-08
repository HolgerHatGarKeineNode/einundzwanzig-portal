<?php

namespace App\Mcp\Tools\City;

use App\Http\Requests\Api\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\City;
use App\Models\Country;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert eine deiner Städte (per Name angegeben). Nur der Ersteller oder ein Super-Admin darf sie ändern.')]
class UpdateCityTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $city = $this->resolveOwnedByName($request, City::class, 'Städte', 'city');

        if ($city instanceof Response) {
            return $city;
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $city)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diese Stadt ändern.');
        }

        if ($error = $this->mergeForeignKey($request, 'country', 'country_id', Country::query(), 'Land', false)) {
            return $error;
        }

        $validated = $request->validate((new UpdateCityRequest)->rules());

        $city->update($validated);

        return Response::json(CityResource::make($city->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'city' => $schema->string()->description('Name der zu ändernden Stadt (aus deinen Städten, siehe list-my-cities).'),
            'id' => $schema->integer()->description('Optional: ID der Stadt, falls bereits bekannt (Alternative zu "city").'),
            'country' => $schema->string()->description('Name des zugehörigen Landes (wird automatisch aufgelöst).'),
            'country_id' => $schema->integer()->description('Optional: ID des Landes (Alternative zu "country").'),
            'name' => $schema->string()->description('Neuer Name der Stadt.'),
            'longitude' => $schema->number()->description('Längengrad der Stadt.'),
            'latitude' => $schema->number()->description('Breitengrad der Stadt.'),
            'population' => $schema->integer()->description('Einwohnerzahl der Stadt.'),
        ];
    }
}
