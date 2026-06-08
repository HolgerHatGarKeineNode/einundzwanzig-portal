<?php

namespace App\Mcp\Tools\City;

use App\Http\Requests\Api\StoreCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Legt eine neue Stadt für den authentifizierten Nutzer an. Der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateCityTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('create', City::class)) {
            return Response::error('Nicht berechtigt, eine Stadt anzulegen.');
        }

        $storeRequest = new StoreCityRequest;

        $validated = $request->validate(
            $storeRequest->rules(),
            $storeRequest->messages(),
        );

        $city = City::create($validated);

        return Response::json(CityResource::make($city->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'country_id' => $schema->integer()->description('ID des zugehörigen Landes.')->required(),
            'name' => $schema->string()->description('Name der Stadt.')->required(),
            'longitude' => $schema->number()->description('Längengrad der Stadt.')->required(),
            'latitude' => $schema->number()->description('Breitengrad der Stadt.')->required(),
            'population' => $schema->integer()->description('Einwohnerzahl der Stadt.'),
        ];
    }
}
