<?php

namespace App\Mcp\Tools\City;

use App\Http\Requests\Api\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert eine bestehende Stadt. Nur der Ersteller oder ein Super-Admin darf sie ändern.')]
class UpdateCityTool extends Tool
{
    public function handle(Request $request): Response
    {
        $city = City::find($request->get('id'));

        if (! $city) {
            return Response::error('Stadt nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $city)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diese Stadt ändern.');
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
            'id' => $schema->integer()->description('ID der zu aktualisierenden Stadt.')->required(),
            'country_id' => $schema->integer()->description('ID des zugehörigen Landes.'),
            'name' => $schema->string()->description('Name der Stadt.'),
            'longitude' => $schema->number()->description('Längengrad der Stadt.'),
            'latitude' => $schema->number()->description('Breitengrad der Stadt.'),
            'population' => $schema->integer()->description('Einwohnerzahl der Stadt.'),
        ];
    }
}
