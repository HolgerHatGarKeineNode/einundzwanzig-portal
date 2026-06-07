<?php

namespace App\Policies;

use App\Models\City;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class CityPolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, City $city): bool
    {
        return $this->owns($user, $city);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, City $city): bool
    {
        return $this->owns($user, $city);
    }
}
