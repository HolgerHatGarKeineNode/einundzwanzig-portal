<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cookie;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class Venue extends Model implements HasMedia
{
    use HasFactory;
    use HasRelationships;
    use HasSlug;
    use InteractsWithMedia;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'city_id' => 'integer',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('preview')
            ->fit(Fit::Crop, 300, 300)
            ->nonQueued();
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 130, 130)
            ->width(130)
            ->height(130);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useFallbackUrl(asset('img/einundzwanzig.png'));
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['city.slug', 'name'])
            ->saveSlugsTo('slug')
            ->usingLanguage(Cookie::get('lang', config('app.locale')));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function lecturers()
    {
        return $this->hasManyDeepFromRelations($this->courses(), (new Course)->lecturer());
    }

    public function courses()
    {
        return $this->hasManyDeepFromRelations($this->events(), (new CourseEvent)->course());
    }

    public function courseEvents(): HasMany
    {
        return $this->hasMany(CourseEvent::class);
    }
}
