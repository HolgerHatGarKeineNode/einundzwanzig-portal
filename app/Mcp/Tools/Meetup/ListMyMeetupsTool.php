<?php

namespace App\Mcp\Tools\Meetup;

use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Listet alle vom authentifizierten Nutzer erstellten Meetups, alphabetisch sortiert.')]
class ListMyMeetupsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('viewAny', Meetup::class)) {
            return Response::error('Nicht authentifiziert.');
        }

        $meetups = Meetup::query()
            ->where('created_by', $user->getAuthIdentifier())
            ->orderBy('name')
            ->get();

        return Response::json(MeetupResource::collection($meetups)->resolve());
    }
}
