<?php

namespace App\Mcp\Tools\Meetup;

use App\Http\Requests\Api\StoreMeetupRequest;
use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Legt ein neues Meetup für den authentifizierten Nutzer an. Der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateMeetupTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('create', Meetup::class)) {
            return Response::error('Nicht berechtigt, ein Meetup anzulegen.');
        }

        $storeRequest = new StoreMeetupRequest;

        $validated = $request->validate(
            $storeRequest->rules(),
            $storeRequest->messages(),
        );

        $meetup = Meetup::create($validated);

        return Response::json(MeetupResource::make($meetup->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name des Meetups.')->required(),
            'city_id' => $schema->integer()->description('ID der zugehörigen Stadt (vorher per search-cities auflösen).')->required(),
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
