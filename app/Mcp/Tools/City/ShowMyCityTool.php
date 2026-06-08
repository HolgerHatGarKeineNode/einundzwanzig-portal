<?php

namespace App\Mcp\Tools\City;

use App\Http\Resources\CityResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\City;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Zeigt eine deiner Städte (per Name angegeben).')]
class ShowMyCityTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $city = $this->resolveOwnedByName($request, City::class, 'Städte', 'city');

        if ($city instanceof Response) {
            return $city;
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('view', $city)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diese Stadt sehen.');
        }

        return Response::json(CityResource::make($city)->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'city' => $schema->string()->description('Name der Stadt (aus deinen Städten, siehe list-my-cities).'),
            'id' => $schema->integer()->description('Optional: ID der Stadt, falls bereits bekannt (Alternative zu "city").'),
        ];
    }
}
