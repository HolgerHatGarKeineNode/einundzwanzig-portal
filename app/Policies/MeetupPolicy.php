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

    public function create(User $user): bool
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
