<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SetsCreatedBy
{
    public static function bootSetsCreatedBy(): void
    {
        static::creating(function (Model $model): void {
            if (auth()->check() && ! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }
}
