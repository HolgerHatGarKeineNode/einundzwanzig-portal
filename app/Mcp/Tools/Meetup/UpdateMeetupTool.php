<?php

namespace App\Mcp\Tools\Meetup;

use App\Http\Requests\Api\UpdateMeetupRequest;
use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert ein bestehendes Meetup. Nur der Ersteller oder ein Super-Admin darf es ändern.')]
class UpdateMeetupTool extends Tool
{
    public function handle(Request $request): Response
    {
        $meetup = Meetup::find($request->get('id'));

        if (! $meetup) {
            return Response::error('Meetup nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $meetup)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf dieses Meetup ändern.');
        }

        $validated = $request->validate((new UpdateMeetupRequest)->rules());

        $meetup->update($validated);

        return Response::json(MeetupResource::make($meetup->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des zu aktualisierenden Meetups.')->required(),
            'name' => $schema->string()->description('Name des Meetups.'),
            'city_id' => $schema->integer()->description('ID der zugehörigen Stadt.'),
            'intro' => $schema->string()->description('Einleitungstext.'),
            'telegram_link' => $schema->string()->description('Telegram-Gruppen-URL.'),
            'webpage' => $schema->string()->description('Webseiten-URL.'),
            'twitter_username' => $schema->string()->description('Twitter/X-Benutzername.'),
            'matrix_group' => $schema->string()->description('Matrix-Gruppe.'),
            'nostr' => $schema->string()->description('Nostr-Identifier.'),
            'simplex' => $schema->string()->description('SimpleX-Link.'),
            'signal' => $schema->string()->description('Signal-Gruppenlink.'),
            'community' => $schema->string()->description('Community-Bezeichnung.'),
            'visible_on_map' => $schema->boolean()->description('Auf der Karte sichtbar.'),
            'is_active' => $schema->boolean()->description('Aktiv.'),
        ];
    }
}
