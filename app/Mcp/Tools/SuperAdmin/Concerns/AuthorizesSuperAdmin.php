<?php

namespace App\Mcp\Tools\SuperAdmin\Concerns;

use App\Mcp\Support\SuperAdminModels;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Gemeinsame Autorisierung für die generischen Super-Admin-Tools: Sie werden nur für
 * angemeldete Super-Admins registriert (shouldRegister) und prüfen die Rolle zusätzlich
 * in handle() (Defense in Depth).
 */
trait AuthorizesSuperAdmin
{
    /**
     * Tool nur registrieren, wenn der verbundene Nutzer ein Super-Admin ist – andere
     * Nutzer sehen die Super-Admin-Tools gar nicht erst.
     */
    public function shouldRegister(Request $request): bool
    {
        return $this->superAdmin($request) !== null;
    }

    private function superAdmin(?Request $request): ?User
    {
        $user = $request?->user();

        return $user instanceof User && $user->hasRole('super-admin') ? $user : null;
    }

    private function denyUnlessSuperAdmin(Request $request): ?Response
    {
        return $this->superAdmin($request) === null
            ? Response::error('Diese Funktion ist nur für Super-Admins verfügbar.')
            : null;
    }

    /**
     * Löst den 'model'-Parameter zur Eloquent-Klasse auf oder liefert eine Auswahlliste.
     *
     * @return class-string<Model>|Response
     */
    private function resolveModel(Request $request): string|Response
    {
        $class = SuperAdminModels::resolve($request->get('model'));

        if ($class === null) {
            return Response::error(
                'Unbekanntes Model: "'.$request->get('model').'". Verfügbar: '
                .implode(', ', SuperAdminModels::keys()).'.'
            );
        }

        return $class;
    }

    /**
     * Lehnt das Setzen sicherheitskritischer Felder (Passwörter, Auth-Tokens, Rollen)
     * über die generischen Super-Admin-Tools ab.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function rejectProtectedAttributes(array $attributes): ?Response
    {
        $blocked = array_values(array_filter(
            array_keys($attributes),
            fn (string $key): bool => in_array(strtolower($key), SuperAdminModels::PROTECTED_ATTRIBUTES, true)
        ));

        if ($blocked !== []) {
            return Response::error(
                'Diese Felder können nicht über die Super-Admin-Tools geändert werden: '
                .implode(', ', $blocked).'. Passwörter und Rollen werden über die dafür '
                .'vorgesehenen Wege verwaltet.'
            );
        }

        return null;
    }

    /**
     * Gemeinsamer "model"-Parameter für die Super-Admin-Tools.
     */
    private function modelParameter(JsonSchema $schema): Type
    {
        return $schema->string()
            ->description('Model-Name, Kurzform oder Tabelle (z. B. "meetup", "Meetup" oder "meetups"). Siehe super-admin-list-models.')
            ->required();
    }
}
