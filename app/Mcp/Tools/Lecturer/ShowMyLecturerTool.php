<?php

namespace App\Mcp\Tools\Lecturer;

use App\Http\Resources\LecturerResource;
use App\Models\Lecturer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Zeigt einen einzelnen, vom authentifizierten Nutzer erstellten Referenten.')]
class ShowMyLecturerTool extends Tool
{
    public function handle(Request $request): Response
    {
        $lecturer = Lecturer::find($request->get('id'));

        if (! $lecturer) {
            return Response::error('Referent nicht gefunden.');
        }

        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('view', $lecturer)) {
            return Response::error('Nur der Ersteller oder ein Super-Admin darf diesen Referenten sehen.');
        }

        return Response::json(LecturerResource::make($lecturer)->resolve());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID des Referenten.')->required(),
        ];
    }
}
