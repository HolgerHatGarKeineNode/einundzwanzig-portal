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
 * Vergibt (oder entzieht) die Rolle „meetup-steward" an den Nutzer mit dem
 * angegebenen npub. Ein Steward darf die Stammdaten JEDES Meetups bearbeiten
 * und für jedes Meetup Leader einsetzen/entziehen (MeetupPolicy::update()
 * und ::manageLeaders()).
 *
 * Bewusst NICHT umgesetzt: created_by der Meetups umschreiben oder den Steward
 * in die meetup_user-Pivot eintragen. Beides würde ihn in „Meine Meetups"
 * einhängen (Meetup::scopeAssociatedWith() liest created_by ODER Pivot,
 * User::meetups() die Pivot) und seine Liste mit allen Meetups fluten —
 * außerdem ginge die Ersteller-Zuordnung der echten Organisatoren verloren.
 */
#[Signature('meetups:grant-steward {npub : npub des Nutzers} {--revoke : Rolle entziehen statt vergeben} {--create : Account anlegen, falls der npub noch unbekannt ist}')]
#[Description('Vergibt die Rolle meetup-steward (Leader-Verwaltung für ALLE Meetups) an einen npub.')]
class GrantMeetupSteward extends Command
{
    public function handle(): int
    {
        $npub = (string) $this->argument('npub');

        $validator = Validator::make(['npub' => $npub], ['npub' => ['required', 'string', new ValidNpub]]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('npub'));

            return Command::FAILURE;
        }

        $role = Role::findOrCreate(User::ROLE_MEETUP_STEWARD, 'web');

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

        // Fail-closed: ein vertippter (aber bech32-gültiger) npub darf nicht still
        // einen frischen Account mit plattformweiter Rolle erzeugen. Anlegen nur
        // auf ausdrückliche Ansage per --create.
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
        $this->line('Der Nutzer kann ab sofort für jedes Meetup Leader einsetzen und entziehen — „Meine Meetups" bleibt unverändert.');

        return Command::SUCCESS;
    }
}
