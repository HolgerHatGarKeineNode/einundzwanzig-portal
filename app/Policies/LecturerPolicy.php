<?php

namespace App\Policies;

use App\Models\Lecturer;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class LecturerPolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lecturer $lecturer): bool
    {
        return $this->owns($user, $lecturer);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lecturer $lecturer): bool
    {
        return $this->owns($user, $lecturer);
    }
}
