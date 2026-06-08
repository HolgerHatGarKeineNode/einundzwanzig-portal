<?php

namespace App\Mcp\Tools\City;

use App\Http\Resources\CityResource;
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
#[Description('Zeigt eine einzelne, vom authentifizierten Nutzer erstellte Stadt.')]
class ShowMyCityTool extends Tool
{
    public function handle(Request $request): Response
    {
        $city = City::find($request->get('id'));

        if (! $city) {
            return Response::error('Stadt nicht gefunden.');
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
            'id' => $schema->integer()->description('ID der Stadt.')->required(),
        ];
    }
}
