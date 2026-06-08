<?php

namespace App\Mcp\Tools\SuperAdmin;

use App\Mcp\Support\SuperAdminModels;
use App\Mcp\Tools\SuperAdmin\Concerns\AuthorizesSuperAdmin;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('NUR SUPER-ADMIN: Listet alle bearbeitbaren Models (key, Klasse, Tabelle). Ausgangspunkt, um anschließend per super-admin-describe-model die Felder zu sehen und Datensätze zu bearbeiten.')]
class SuperAdminListModelsTool extends Tool
{
    use AuthorizesSuperAdmin;

    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        return Response::json(SuperAdminModels::list());
    }
}
