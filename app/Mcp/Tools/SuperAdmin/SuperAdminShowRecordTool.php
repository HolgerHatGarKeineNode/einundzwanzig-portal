<?php

namespace App\Mcp\Tools\SuperAdmin;

use App\Mcp\Tools\SuperAdmin\Concerns\AuthorizesSuperAdmin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('NUR SUPER-ADMIN: Zeigt einen einzelnen Datensatz eines beliebigen Models per ID (alle Attribute).')]
class SuperAdminShowRecordTool extends Tool
{
    use AuthorizesSuperAdmin;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $class = $this->resolveModel($request);

        if ($class instanceof Response) {
            return $class;
        }

        $record = $class::query()->find($request->get('id'));

        if ($record === null) {
            return Response::error('Datensatz mit ID '.$request->get('id').' in '.class_basename($class).' nicht gefunden.');
        }

        return Response::json($record->toArray());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $this->modelParameter($schema),
            'id' => $schema->integer()->description('Primärschlüssel des Datensatzes.')->required(),
        ];
    }
}
