<?php

namespace App\Mcp\Tools\Lecturer;

use App\Http\Requests\Api\UpdateLecturerRequest;
use App\Http\Resources\LecturerResource;
use App\Mcp\Tools\Concerns\ResolvesEntities;
use App\Models\Lecturer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Aktualisiert einen deiner Referenten (per Name angegeben). Nur der Ersteller oder ein Super-Admin darf ihn ändern.')]
class UpdateLecturerTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $lecturer = $this->resolveOwnedByName($request, Lecturer::class, 'Referenten', 'lecturer');

        if ($lecturer instanceof Response) {
            return $lecturer;
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
            'lecturer' => $schema->string()->description('Name des zu ändernden Referenten (aus deinen Referenten, siehe list-my-lecturers).'),
            'id' => $schema->integer()->description('Optional: ID des Referenten, falls bereits bekannt (Alternative zu "lecturer").'),
            'name' => $schema->string()->description('Neuer Name des Referenten.'),
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
