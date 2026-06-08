<?php

namespace App\Mcp\Tools\Lecturer;

use App\Http\Requests\Api\UpdateLecturerRequest;
use App\Http\Resources\LecturerResource;
use App\Models\Lecturer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert einen bestehenden Referenten. Nur der Ersteller oder ein Super-Admin darf ihn ändern.')]
class UpdateLecturerTool extends Tool
{
    public function handle(Request $request): Response
    {
        $lecturer = Lecturer::find($request->get('id'));

        if (! $lecturer) {
            return Response::error('Referent nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('update', $lecturer)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diesen Referenten ändern.');
        }

        $validated = $request->validate((new UpdateLecturerRequest)->rules());

        $lecturer->update($validated);

        return Response::json(LecturerResource::make($lecturer->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des zu aktualisierenden Referenten.')->required(),
            'name' => $schema->string()->description('Name des Referenten.'),
            'subtitle' => $schema->string()->description('Untertitel.'),
            'intro' => $schema->string()->description('Einleitungstext.'),
            'description' => $schema->string()->description('Beschreibung.'),
            'active' => $schema->boolean()->description('Aktiv.'),
            'website' => $schema->string()->description('Webseiten-URL.'),
            'twitter_username' => $schema->string()->description('Twitter/X-Benutzername.'),
            'nostr' => $schema->string()->description('Nostr-Identifier.'),
            'lightning_address' => $schema->string()->description('Lightning-Adresse.'),
            'lnurl' => $schema->string()->description('LNURL.'),
            'node_id' => $schema->string()->description('Lightning-Node-ID.'),
            'paynym' => $schema->string()->description('PayNym.'),
            'team_id' => $schema->integer()->description('ID des zugehörigen Teams.'),
        ];
    }
}
