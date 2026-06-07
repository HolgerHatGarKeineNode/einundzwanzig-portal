<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;
use App\Policies\Concerns\ChecksCreatorOwnership;

class VenuePolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Venue $venue): bool
    {
        return $this->owns($user, $venue);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Venue $venue): bool
    {
        return $this->owns($user, $venue);
    }
}
