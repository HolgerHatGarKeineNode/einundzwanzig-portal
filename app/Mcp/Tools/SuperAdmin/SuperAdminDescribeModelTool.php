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
#[Description('NUR SUPER-ADMIN: Beschreibt ein Model: Spalten (Name, Typ, nullable, Default), Primärschlüssel und Casts. So weißt du, welche Felder du bei super-admin-create-record / super-admin-update-record setzen kannst.')]
class SuperAdminDescribeModelTool extends Tool
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
        $table = $model->getTable();

        $columns = collect(Schema::getColumns($table))->map(fn (array $column): array => [
            'name' => $column['name'],
            'type' => $column['type_name'] ?? $column['type'] ?? null,
            'nullable' => $column['nullable'] ?? null,
            'default' => $column['default'] ?? null,
        ])->values();

        return Response::json([
            'model' => class_basename($class),
            'class' => $class,
            'table' => $table,
            'primary_key' => $model->getKeyName(),
            'columns' => $columns,
            'casts' => $model->getCasts(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $this->modelParameter($schema),
        ];
    }
}
