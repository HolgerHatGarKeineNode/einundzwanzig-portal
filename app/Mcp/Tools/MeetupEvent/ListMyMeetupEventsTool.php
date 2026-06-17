<?php

namespace App\Mcp\Tools\MeetupEvent;

use App\Http\Resources\MeetupEventResource;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Listet alle Meetup-Termine, die der authentifizierte Nutzer bearbeiten darf (selbst angelegt oder Leader des Meetups), nach Startzeitpunkt absteigend sortiert.')]
class ListMyMeetupEventsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('viewAny', MeetupEvent::class)) {
            return Response::error('Nicht authentifiziert.');
        }

        $meetupEvents = MeetupEvent::query()
            ->editableBy((int) $user->getAuthIdentifier())
            ->orderByDesc('start')
            ->get();

        return Response::json(MeetupEventResource::collection($meetupEvents)->resolve());
    }
}
