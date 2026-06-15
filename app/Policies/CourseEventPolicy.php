<?php

namespace App\Policies;

use App\Models\CourseEvent;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class CourseEventPolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CourseEvent $courseEvent): bool
    {
        return $this->owns($user, $courseEvent);
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_lecturer;
    }

    public function update(User $user, CourseEvent $courseEvent): bool
    {
        return $this->owns($user, $courseEvent);
    }
}
