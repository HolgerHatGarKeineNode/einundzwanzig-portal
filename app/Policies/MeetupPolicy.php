<?php

namespace App\Policies;

use App\Models\Meetup;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class MeetupPolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Meetup $meetup): bool
    {
        return $this->owns($user, $meetup);
    }

    /**
     * Sichtbarkeit der „Meine Meetups"-Detailansicht: Neben dem Ersteller darf
     * jedes Mitglied der meetup_user-Pivot („Meine Meetups" im Dashboard) das
     * abonnierte Meetup über die REST-API ansehen. Spiegelt die Listen-Semantik
     * von MeetupController::mine(), die ebenfalls die Pivot-Mitgliedschaft nutzt.
     */
    public function viewMine(User $user, Meetup $meetup): bool
    {
        return $this->owns($user, $meetup) || $meetup->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Ein bestehendes Meetup zu „Meine Meetups" hinzufügen (meetup_user-Pivot als
     * Mitglied, nicht als Leader). Jeder authentifizierte Nutzer darf das — die
     * Stammdaten bleiben dem Ersteller vorbehalten (siehe update()). Spiegelt die
     * offene Semantik des AddMeetupToMineTool (MCP).
     */
    public function addToMine(User $user, Meetup $meetup): bool
    {
        return true;
    }

    /**
     * Ein Meetup wieder aus „Meine Meetups" entfernen (löst die meetup_user-Pivot
     * des Nutzers). Spiegelt die offene Semantik von addToMine(): jeder
     * authentifizierte Nutzer darf seine eigene Zuordnung lösen. Die Stammdaten
     * bleiben dem Ersteller vorbehalten (siehe update()).
     */
    public function removeFromMine(User $user, Meetup $meetup): bool
    {
        return true;
    }

    /**
     * Stammdaten bearbeiten: der Ersteller (bzw. Super-Admin) ODER ein
     * delegierter Leader (meetup_user.is_leader = true). Die bloße
     * „Meine Meetups"-Mitgliedschaft (is_leader = false) berechtigt NICHT
     * mehr zum Bearbeiten — Leader werden über manageLeaders() vergeben.
     * Gilt einheitlich für REST-API, MCP und Portal-Frontend.
     */
    public function update(User $user, Meetup $meetup): bool
    {
        return $this->owns($user, $meetup) || $meetup->isLeader($user);
    }

    /**
     * Weitere Leader einsetzen/entziehen: nur ein bestehender Leader bzw. der
     * Ersteller/Super-Admin. Delegation ist damit selbsttragend — jeder Leader
     * kann neue Leader benennen (der Ersteller selbst kann nie entzogen werden,
     * das erzwingt der Controller).
     */
    public function manageLeaders(User $user, Meetup $meetup): bool
    {
        return $this->owns($user, $meetup) || $meetup->isLeader($user);
    }
}
