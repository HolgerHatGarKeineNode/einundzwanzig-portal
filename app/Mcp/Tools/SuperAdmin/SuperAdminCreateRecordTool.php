<?php

namespace App\Mcp\Tools\SuperAdmin;

use App\Mcp\Tools\SuperAdmin\Concerns\AuthorizesSuperAdmin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('NUR SUPER-ADMIN: Legt einen Datensatz für ein beliebiges Model an. Die Felder werden als "attributes"-Objekt übergeben (Mass-Assignment-Schutz wird bewusst umgangen). Vorher per super-admin-describe-model die Pflichtfelder prüfen.')]
class SuperAdminCreateRecordTool extends Tool
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

        $attributes = (array) ($request->get('attributes') ?? []);

        if ($attributes === []) {
            return Response::error('Bitte "attributes" mit den zu setzenden Feldern angeben.');
        }

        if ($blocked = $this->rejectProtectedAttributes($attributes)) {
            return $blocked;
        }

        try {
            /** @var Model $record */
            $record = new $class;
            $record->forceFill($attributes)->save();
        } catch (Throwable $e) {
            return Response::error('Anlegen fehlgeschlagen: '.$e->getMessage());
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
            'attributes' => $schema->object()->description('Objekt {spalte: wert} mit den zu setzenden Feldern.')->required(),
        ];
    }
}
