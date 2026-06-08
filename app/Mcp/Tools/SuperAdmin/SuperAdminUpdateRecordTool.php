<?php

namespace App\Mcp\Tools\SuperAdmin;

use App\Mcp\Tools\SuperAdmin\Concerns\AuthorizesSuperAdmin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('NUR SUPER-ADMIN: Aktualisiert einen Datensatz eines beliebigen Models per ID. Die zu ändernden Felder werden als "attributes"-Objekt übergeben (Mass-Assignment-Schutz wird bewusst umgangen).')]
class SuperAdminUpdateRecordTool extends Tool
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

        $attributes = (array) ($request->get('attributes') ?? []);

        if ($attributes === []) {
            return Response::error('Bitte "attributes" mit den zu ändernden Feldern angeben.');
        }

        if ($blocked = $this->rejectProtectedAttributes($attributes)) {
            return $blocked;
        }

        try {
            $record->forceFill($attributes)->save();
        } catch (Throwable $e) {
            return Response::error('Aktualisieren fehlgeschlagen: '.$e->getMessage());
        }

        return Response::json($record->fresh()->toArray());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $this->modelParameter($schema),
            'id' => $schema->integer()->description('Primärschlüssel des zu ändernden Datensatzes.')->required(),
            'attributes' => $schema->object()->description('Objekt {spalte: wert} mit den zu ändernden Feldern.')->required(),
        ];
    }
}
