<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends \Spatie\Tags\Tag
{
    public function courses(): MorphToMany
    {
        return $this->morphedByMany(Course::class, 'taggable');
    }

    public function libraryItems(): MorphToMany
    {
        return $this->morphedByMany(LibraryItem::class, 'taggable');
    }

    public function episodes(): MorphToMany
    {
        return $this->morphedByMany(Episode::class, 'taggable');
    }
}
