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

    public function update(User $user, Meetup $meetup): bool
    {
        return $this->owns($user, $meetup);
    }

    /**
     * Gelockerte Update-Regel ausschließlich für das Portal-Frontend (Livewire):
     * Neben dem Ersteller darf auch jedes Mitglied der meetup_user-Pivot
     * („Meine Meetups" im Dashboard) die Stammdaten bearbeiten. REST-API und
     * MCP nutzen weiterhin die strikte update()-Ability. Übergangslösung, bis
     * ein echtes Rollen-/Freigabekonzept existiert.
     */
    public function updateViaPortal(User $user, Meetup $meetup): bool
    {
        return $this->owns($user, $meetup) || $meetup->hasMember($user);
    }
}
