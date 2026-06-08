<?php

namespace App\Mcp\Tools\MeetupEvent;

use App\Http\Resources\MeetupEventResource;
use App\Models\MeetupEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Zeigt einen einzelnen, vom authentifizierten Nutzer erstellten Meetup-Termin.')]
class ShowMyMeetupEventTool extends Tool
{
    public function handle(Request $request): Response
    {
        $meetupEvent = MeetupEvent::find($request->get('id'));

        if (! $meetupEvent) {
            return Response::error('Meetup-Termin nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('view', $meetupEvent)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diesen Meetup-Termin sehen.');
        }

        return Response::json(MeetupEventResource::make($meetupEvent)->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des Meetup-Termins.')->required(),
        ];
    }
}
