<?php

namespace App\Mcp\Tools\Meetup;

use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Zeigt ein einzelnes, vom authentifizierten Nutzer erstelltes Meetup.')]
class ShowMyMeetupTool extends Tool
{
    public function handle(Request $request): Response
    {
        $meetup = Meetup::find($request->get('id'));

        if (! $meetup) {
            return Response::error('Meetup nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('view', $meetup)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf dieses Meetup sehen.');
        }

        return Response::json(MeetupResource::make($meetup)->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des Meetups.')->required(),
        ];
    }
}
