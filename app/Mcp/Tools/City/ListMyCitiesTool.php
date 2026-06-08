<?php

namespace App\Mcp\Tools\City;

use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Listet alle vom authentifizierten Nutzer erstellten Städte, alphabetisch sortiert.')]
class ListMyCitiesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('viewAny', City::class)) {
            return Response::error('Nicht authentifiziert.');
        }

        $cities = City::query()
            ->where('created_by', $user->getAuthIdentifier())
            ->orderBy('name')
            ->get();

        return Response::json(CityResource::collection($cities)->resolve());
    }
}
