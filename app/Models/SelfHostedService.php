<?php

namespace App\Models;

use App\Enums\SelfHostedServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    use HasSlug;
    use HasTags;
    use InteractsWithMedia;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'intro',
        'url_clearnet',
        'url_onion',
        'url_i2p',
        'url_pkdns',
        'ip',
        'contact',
        'anon',
    ];

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
            if (auth()->check() && ! isset($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name'])
            ->saveSlugsTo('slug')
            /*
             * Feste Sprache statt Cookie::get('lang'): die Transliteration haengt sonst
             * am Nutzer, der gerade speichert — Str::slug macht aus "Koeln" nur im
             * deutschen Modus "koeln", sonst "koln". Gemessen am 2026-08-22: 37 der 314
             * Meetups haetten sich allein dadurch verschoben. Ausserdem greift Cookie::get()
             * in Konsolenbefehlen und Jobs ins Leere.
             */
            ->usingLanguage(config('app.locale'))
            /*
             * Der Slug ist Route-Key (/{country}/service/{service:slug}), er darf sich nie mehr aendern.
             * Ohne diese Zeile erzeugt HasSlug den Slug bei JEDEM Update neu.
             */
            ->doNotGenerateSlugsOnUpdate();
    }

    public function registerMediaConversions(?Media $media = null): void
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
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->singleFile()
            ->useFallbackUrl(asset('img/einundzwanzig.png'));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
