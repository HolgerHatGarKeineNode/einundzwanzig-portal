<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksCreatorOwnership
{
    /**
     * Nur der urspruengliche Ersteller (created_by) oder ein Super-Admin darf das Model veraendern.
     */
    protected function owns(User $user, Model $model): bool
    {
        return (int) $model->created_by === $user->id || $user->hasRole('super-admin');
    }
}
