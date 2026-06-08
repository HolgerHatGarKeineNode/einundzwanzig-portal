<?php

namespace App\Mcp\Tools\Lecturer;

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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Zeigt einen deiner Referenten (per Name angegeben).')]
class ShowMyLecturerTool extends Tool
{
    use ResolvesEntities;

    public function handle(Request $request): Response
    {
        $lecturer = $this->resolveOwnedByName($request, Lecturer::class, 'Referenten', 'lecturer');

        if ($lecturer instanceof Response) {
            return $lecturer;
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
            'lecturer' => $schema->string()->description('Name des Referenten (aus deinen Referenten, siehe list-my-lecturers).'),
            'id' => $schema->integer()->description('Optional: ID des Referenten, falls bereits bekannt (Alternative zu "lecturer").'),
        ];
    }
}
