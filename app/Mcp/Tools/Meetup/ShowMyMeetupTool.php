<?php

namespace App\Mcp\Tools\Meetup;

use App\Http\Resources\MeetupResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\Meetup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Zeigt eines deiner Meetups (selbst erstellt oder beigetreten, per Name angegeben).')]
class ShowMyMeetupTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        $meetup = $this->resolveInScope(
            Meetup::query()->associatedWith($user->getAuthIdentifier()),
            $request,
            'Meetups',
            'meetup',
        );

        if ($meetup instanceof Response) {
            return $meetup;
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
