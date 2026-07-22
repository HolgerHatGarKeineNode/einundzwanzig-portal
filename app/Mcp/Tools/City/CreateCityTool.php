<?php

namespace App\Mcp\Tools\City;

use App\Http\Requests\Api\StoreCityRequest;
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

#[Description('Legt eine neue Stadt für den authentifizierten Nutzer an. Das Land wird über seinen Namen angegeben; der Ersteller (created_by) wird automatisch gesetzt. Städtenamen sind global eindeutig: existiert die Stadt bereits, wird sie mit "already_existed": true zurückgeliefert statt ein Duplikat anzulegen.')]
class CreateCityTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('create', City::class)) {
            return Response::error('Nicht berechtigt, eine Stadt anzulegen.');
        }

        if ($error = $this->mergeForeignKey($request, 'country', 'country_id', Country::query(), 'Land')) {
            return $error;
        }

        $storeRequest = new StoreCityRequest;

        $validated = $request->validate(
            $storeRequest->rules(),
            $storeRequest->messages(),
        );

        $city = City::createOrFindByName($validated);

        return Response::json([
            ...CityResource::make($city->fresh())->resolve(),
            'already_existed' => ! $city->wasRecentlyCreated,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'country' => $schema->string()->description('Name des zugehörigen Landes (z. B. "Deutschland"). Wird automatisch aufgelöst – bei Bedarf per list-countries den genauen Namen ermitteln.'),
            'country_id' => $schema->integer()->description('Optional: ID des Landes, falls bereits bekannt (Alternative zu "country").'),
            'name' => $schema->string()->description('Name der Stadt.')->required(),
            'longitude' => $schema->number()->description('Längengrad der Stadt.')->required(),
            'latitude' => $schema->number()->description('Breitengrad der Stadt.')->required(),
            'population' => $schema->integer()->description('Einwohnerzahl der Stadt.'),
        ];
    }
}
