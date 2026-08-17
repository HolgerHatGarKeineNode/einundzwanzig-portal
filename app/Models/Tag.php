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
     * The locale whose name will actually be shown for a requested locale, or null if
     * the tag carries no name at all.
     *
     * Needed because Spatie's own fallback gives up silently: it only falls back to
     * config('app.fallback_locale') if that language happens to be translated, and
     * `fallbackAny` is off by default. Measured on this codebase, a German-only tag
     * asked for in Czech returns an empty string — and 84 of the 89 production tags
     * are German-only, so the Czech picker would have been a list of blanks.
     *
     * Order: what was asked for, then the app's fallback language, then the configured
     * tag locales in order, then whatever the tag actually has.
     */
    public function displayLocale(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $candidates = array_merge(
            [$locale, config('app.fallback_locale')],
            (array) config('einundzwanzig.tag_locales', []),
            $this->getTranslatedLocales('name'),
        );

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && filled($this->getTranslation('name', $candidate, false))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The name to display, never empty as long as the tag has a name in any language.
     *
     * Pair it with displayLocale() to mark where the text came from — the picker shows
     * that marker so a Czech organiser can tell a German label from a Czech one instead
     * of wondering why a row reads oddly.
     */
    public function displayName(?string $locale = null): string
    {
        $resolved = $this->displayLocale($locale);

        return $resolved === null
            ? ''
            : (string) $this->getTranslation('name', $resolved, false);
    }

    /**
     * Whether the shown name is a substitute rather than the requested language.
     */
    public function isDisplayNameSubstituted(?string $locale = null): bool
    {
        $locale ??= app()->getLocale();

        return $this->displayLocale($locale) !== $locale;
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
