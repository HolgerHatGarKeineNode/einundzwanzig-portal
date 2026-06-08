<?php

namespace App\Mcp\Tools\Lecturer;

use App\Http\Requests\Api\StoreLecturerRequest;
use App\Http\Resources\LecturerResource;
use App\Models\Lecturer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Legt einen neuen Referenten für den authentifizierten Nutzer an. Der Ersteller (created_by) wird automatisch gesetzt.')]
class CreateLecturerTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('create', Lecturer::class)) {
            return Response::error('Nicht berechtigt, einen Referenten anzulegen.');
        }

        $storeRequest = new StoreLecturerRequest;

        $validated = $request->validate(
            $storeRequest->rules(),
            $storeRequest->messages(),
        );

        $lecturer = Lecturer::create($validated);

        return Response::json(LecturerResource::make($lecturer->fresh())->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name des Referenten.')->required(),
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
