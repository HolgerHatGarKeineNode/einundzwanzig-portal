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

#[Description('Fügt ein bereits bestehendes Meetup (per Name angegeben) zu „Meine Meetups" hinzu, sodass es in der eigenen Liste erscheint und Termine dazu angelegt werden können. Macht den Nutzer zum Mitglied – die Stammdaten (Name, Stadt, Links) bleiben dem ursprünglichen Ersteller vorbehalten. Vorher mit search-meetups den genauen Namen ermitteln.')]
class AddMeetupToMineTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Nicht authentifiziert.');
        }

        if ($this->present($request->get('meetup_id'))) {
            $meetup = Meetup::find($request->get('meetup_id'));

            if ($meetup === null) {
                return Response::error('Meetup nicht gefunden.');
            }
        } else {
            $meetup = $this->resolveGlobalByName(Meetup::query(), $request->get('meetup'), 'Meetups');

            if ($meetup instanceof Response) {
                return $meetup;
            }
        }

        $alreadyMember = $meetup->users()->whereKey($user->getAuthIdentifier())->exists();

        $meetup->users()->syncWithoutDetaching([
            $user->getAuthIdentifier() => ['is_leader' => false],
        ]);

        return Response::json([
            'meetup' => MeetupResource::make($meetup)->resolve(),
            'message' => $alreadyMember
                ? '„'.$meetup->name.'" war bereits Teil deiner Meetups.'
                : '„'.$meetup->name.'" wurde zu deinen Meetups hinzugefügt.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'meetup' => $schema->string()->description('Name des bestehenden Meetups, das hinzugefügt werden soll (vorher per search-meetups ermitteln).'),
            'meetup_id' => $schema->integer()->description('Optional: ID des Meetups, falls bereits bekannt (Alternative zu "meetup").'),
        ];
    }
}
