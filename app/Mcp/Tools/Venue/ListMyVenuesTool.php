<?php

namespace App\Mcp\Tools\Venue;

use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Listet alle vom authentifizierten Nutzer erstellten Veranstaltungsorte, alphabetisch sortiert.')]
class ListMyVenuesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('viewAny', Venue::class)) {
            return Response::error('Nicht authentifiziert.');
        }

        $venues = Venue::query()
            ->where('created_by', $user->getAuthIdentifier())
            ->orderBy('name')
            ->get();

        return Response::json(VenueResource::collection($venues)->resolve());
    }
}
