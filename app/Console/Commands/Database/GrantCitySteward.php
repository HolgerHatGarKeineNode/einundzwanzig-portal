<?php

namespace App\Console\Commands\Database;

use App\Models\User;
use App\Rules\ValidNpub;
use App\Support\NostrLogin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Vergibt (oder entzieht) die Rolle „city-steward" an den Nutzer mit dem angegebenen
 * npub. Ein City-Steward darf die IDENTITAETSFELDER jeder Stadt aendern: Name, Land,
 * Region, Einwohnerzahl und deren Stichjahr (CityPolicy::updateIdentity()).
 *
 * Was die Rolle NICHT ist: eine Erlaubnis zum Bearbeiten ueberhaupt. Anreichern —
 * OSM-Referenz, Wikidata, Wikipedia, Koordinaten — darf seit Issue #30 jeder
 * angemeldete Nutzer, ohne Rolle und ohne Antrag.
 *
 * Bewusst NICHT umgesetzt: `created_by` der Staedte umschreiben. Das wuerde die
 * Herkunft der echten Ersteller ueberschreiben, dem Steward alle Staedte in „Meine
 * Staedte" spuelen — und, weil `cities.created_by` eine Loeschkaskade traegt, seine
 * Kontoloeschung an den Bestand fremder Meetups haengen. Spiegelt dieselbe
 * Zurueckhaltung wie meetups:grant-steward.
 */
#[Signature('cities:grant-steward {npub : npub des Nutzers} {--revoke : Rolle entziehen statt vergeben} {--create : Account anlegen, falls der npub noch unbekannt ist}')]
#[Description('Vergibt die Rolle city-steward (Identitaetsfelder ALLER Staedte) an einen npub.')]
class GrantCitySteward extends Command
{
    public function handle(): int
    {
        $npub = (string) $this->argument('npub');

        $validator = Validator::make(['npub' => $npub], ['npub' => ['required', 'string', new ValidNpub]]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('npub'));

            return Command::FAILURE;
        }

        $role = Role::findOrCreate(User::ROLE_CITY_STEWARD, 'web');

        if ($this->option('revoke')) {
            $user = User::query()->where('nostr', $npub)->first();

            if ($user === null) {
                $this->error("Kein Nutzer mit npub {$npub} gefunden.");

                return Command::FAILURE;
            }

            $user->removeRole($role);

            $this->info("Rolle {$role->name} von {$user->name} ({$npub}) entzogen.");

            return Command::SUCCESS;
        }

        $user = User::query()->where('nostr', $npub)->first();

        // Fail-closed, wie bei meetups:grant-steward: ein vertippter (aber
        // bech32-gueltiger) npub darf nicht still einen frischen Account mit
        // plattformweiter Rolle erzeugen.
        if ($user === null && ! $this->option('create')) {
            $this->error("Kein Nutzer mit npub {$npub} gefunden. Mit --create einen Account anlegen.");

            return Command::FAILURE;
        }

        $created = $user === null;

        $user ??= NostrLogin::findOrCreateUser($npub);

        $user->assignRole($role);

        if ($created) {
            $this->warn("Neuer Account angelegt (Name: {$user->name}) — er wird beim ersten Nostr-Login übernommen.");
        }

        $this->info("Rolle {$role->name} an {$user->name} ({$npub}) vergeben.");
        $this->line('Der Nutzer kann ab sofort Name, Land, Region, Einwohnerzahl und Stichjahr JEDER Stadt ändern.');
        $this->line('Das Anreichern (OSM, Wikidata, Wikipedia, Koordinaten) steht ohnehin jedem offen — dafür braucht es die Rolle nicht.');

        return Command::SUCCESS;
    }
}
