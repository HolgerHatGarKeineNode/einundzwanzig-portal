<?php

namespace App\Mcp\Tools\SuperAdmin;

use App\Mcp\Tools\SuperAdmin\Concerns\AuthorizesSuperAdmin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('NUR SUPER-ADMIN: Listet Datensätze eines Models (neueste zuerst), optional gefiltert nach exakten Spaltenwerten. Zum Finden der zu bearbeitenden Datensätze und ihrer IDs.')]
class SuperAdminListRecordsTool extends Tool
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

        /** @var Model $model */
        $model = new $class;
        $columns = Schema::getColumnListing($model->getTable());

        $filters = collect((array) ($request->get('filters') ?? []))
            ->only($columns);

        $limit = max(1, min(100, (int) ($request->get('limit') ?? 25)));

        $records = $class::query()
            ->where($filters->all())
            ->latest($model->getKeyName())
            ->limit($limit)
            ->get();

        return Response::json([
            'model' => class_basename($class),
            'count' => $records->count(),
            'records' => $records->map->toArray()->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $this->modelParameter($schema),
            'filters' => $schema->object()->description('Optionale exakte Filter als Objekt {spalte: wert}. Unbekannte Spalten werden ignoriert.'),
            'limit' => $schema->integer()->description('Maximale Anzahl Datensätze (1–100, Default 25).'),
        ];
    }
}
