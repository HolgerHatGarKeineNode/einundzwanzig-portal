<?php

namespace App\Models;

use App\Enums\SelfHostedServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cookie;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;

class SelfHostedService extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use HasSlug;
    use HasTags;

    protected $guarded = [];

    protected $casts = [
        'id' => 'integer',
        'created_by' => 'integer',
        'type' => SelfHostedServiceType::class,
        'anon' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model): void {
            // Only set created_by if user is authenticated and not explicitly set as anonymous
            if (auth()->check() && !isset($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name'])
            ->saveSlugsTo('slug')
            ->usingLanguage(Cookie::get('lang', config('app.locale')));
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this
            ->addMediaConversion('preview')
            ->fit(Fit::Crop, 300, 300)
            ->nonQueued();
        $this
            ->addMediaConversion('thumb')
            ->fit(Fit::Crop, 130, 130)
            ->width(130)
            ->height(130);
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('logo')
            ->singleFile()
            ->useFallbackUrl(asset('img/einundzwanzig.png'));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
