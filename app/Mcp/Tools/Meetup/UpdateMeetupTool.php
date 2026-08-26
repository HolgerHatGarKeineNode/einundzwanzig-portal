<?php

namespace App\Mcp\Tools\Meetup;

use App\Http\Requests\Api\UpdateMeetupRequest;
use App\Http\Resources\MeetupResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\City;
use App\Models\Meetup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert eines deiner Meetups (per Name angegeben). Nur der Ersteller oder ein Super-Admin darf es ändern.')]
class UpdateMeetupTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $meetup = $this->resolveOwnedByName($request, Meetup::class, 'Meetups', 'meetup');

        if ($meetup instanceof Response) {
            return $meetup;
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $meetup)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf dieses Meetup ändern.');
        }

        if ($error = $this->mergeForeignKey($request, 'city', 'city_id', City::query(), 'Stadt', false)) {
            return $error;
        }

        $validated = $request->validate((new UpdateMeetupRequest)->rules($meetup->getKey()));

        $meetup->update($validated);

        return Response::json(MeetupResource::make($meetup->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'meetup' => $schema->string()->description('Name des zu ändernden Meetups (aus deinen Meetups, siehe list-my-meetups).'),
            'id' => $schema->integer()->description('Optional: ID des Meetups, falls bereits bekannt (Alternative zu "meetup").'),
            'name' => $schema->string()->description('Neuer Name des Meetups.'),
            'city' => $schema->string()->description('Name der zugehörigen Stadt (wird automatisch aufgelöst).'),
            'city_id' => $schema->integer()->description('Optional: ID der Stadt (Alternative zu "city").'),
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
        ];
    }
}
