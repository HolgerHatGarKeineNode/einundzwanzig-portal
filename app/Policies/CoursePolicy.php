<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class CoursePolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_lecturer;
    }

    public function update(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }
}
