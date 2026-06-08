<?php

namespace App\Mcp\Tools\Meetup;

use App\Http\Resources\MeetupResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
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
#[Description('Zeigt eines deiner Meetups (per Name angegeben).')]
class ShowMyMeetupTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $meetup = $this->resolveOwnedByName($request, Meetup::class, 'Meetups', 'meetup');

        if ($meetup instanceof Response) {
            return $meetup;
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
            'meetup' => $schema->string()->description('Name des Meetups (aus deinen Meetups, siehe list-my-meetups).'),
            'id' => $schema->integer()->description('Optional: ID des Meetups, falls bereits bekannt (Alternative zu "meetup").'),
        ];
    }
}
