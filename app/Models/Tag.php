<?php

namespace App\Models;

use App\Models\Concerns\SetsCreatedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends \Spatie\Tags\Tag
{
    use HasFactory;
    use SetsCreatedBy;

    /** @var array<int, string> */
    public array $translatable = ['name', 'slug', 'description'];

    /** @var array<string, string> */
    protected $casts = [
        'featured' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Generate a slug per locale, but never rewrite one that already exists.
     *
     * Spatie's own HasSlug trait regenerates every translated slug on every save,
     * which makes a tag's public URL a function of whatever the name currently is —
     * and of the request's locale. This project has been bitten by exactly that
     * before (commit fd48fa7, where an API patch to an unrelated field silently
     * rewrote "nuernberg" to "nurnberg"; see SlugLocaleStabilityApiTest).
     *
     * Laravel's bootTraits() resolves boot methods through late static binding, so
     * defining bootHasSlug() here replaces the trait's version for this model.
     */
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            foreach ($model->getTranslatedLocales('name') as $locale) {
                if (filled($model->getTranslation('slug', $locale, false))) {
                    continue;
                }

                $model->setTranslation('slug', $locale, $model->generateSlug($locale));
            }
        });
    }

    /**
     * Tags offered in the picker before the user types anything.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    /**
     * Tags a tag editor has signed off on.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    /**
     * Tags suggested by someone without the editor permission, awaiting review.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('approved_at');
    }

    /**
     * Tags this user is allowed to see offered: everything approved, plus their own
     * pending suggestions. Without the second half a suggester could not re-select
     * the tag they just proposed.
     */
    public function scopeSelectableBy(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->whereNotNull('approved_at');

            if ($user !== null) {
                $query->orWhere('created_by', $user->id);
            }
        });
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function approve(): bool
    {
        return $this->forceFill(['approved_at' => now()])->save();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function courses()
    {
        return $this->morphedByMany(Course::class, 'taggable');
    }

    public function libraryItems()
    {
        return $this->morphedByMany(LibraryItem::class, 'taggable');
    }

    public function episodes()
    {
        return $this->morphedByMany(Episode::class, 'taggable');
    }
}
