<?php

use App\Http\Middleware\EnsureMcpScope;
use App\Mcp\Servers\EinundzwanzigServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
 * OAuth 2.1 Discovery- und Dynamic-Client-Registration-Routen (RFC 8414 / 9728).
 * Notwendig, damit Web-Clients wie der Claude.ai-Connector den Server automatisch
 * entdecken und den Authorization-Code-Flow (über Laravel Passport) starten können.
 * Gedrosselt, um Client-Registrierungs-Spam zu begrenzen (die Discovery-Endpunkte
 * werden von Clients ohnehin gecacht).
 */
Route::middleware('throttle:30,1')->group(function (): void {
    Mcp::oauthRoutes();
});

/*
 * Authentifizierter MCP-Server. Spiegelt die Sanctum-geschützten API-Schreib-Endpunkte,
 * damit AI-Clients Datensätze im Kontext des angemeldeten Nutzers anlegen/aktualisieren
 * können (created_by wird automatisch gesetzt).
 *
 * Zwei Auth-Wege werden unterstützt:
 *  - auth:sanctum → statisches Sanctum-Token im Authorization: Bearer Header
 *                   (Claude Code / Claude Desktop / Messages-API).
 *  - auth:api    → Passport OAuth-2.1-Access-Token (Claude.ai Web-Connector).
 * Reihenfolge ist wichtig: Sanctum lehnt fremde Tokens still ab und fällt zu Passport
 * durch, während der Passport-Guard bei einem Sanctum-Token eine Exception werfen würde.
 * Der zuerst erfolgreiche Guard wird verwendet; in beiden Fällen liefert auth()->id()
 * den angemeldeten Nutzer für die created_by-Zuordnung.
 *
 * EnsureMcpScope erzwingt anschließend den Scope "mcp:use" (Passport) bzw. die
 * Standard-Ability "*" (Sanctum).
 */
Mcp::web('/mcp', EinundzwanzigServer::class)
    ->middleware(['auth:sanctum,api', EnsureMcpScope::class, 'throttle:60,1']);
