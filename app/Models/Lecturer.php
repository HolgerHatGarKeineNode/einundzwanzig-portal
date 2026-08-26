<?php

namespace App\Models;

use App\Models\Concerns\NormalizesText;
use App\Models\Concerns\SetsCreatedBy;
use App\Observers\ApiChangeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[ObservedBy([ApiChangeObserver::class])]
class Lecturer extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;
    use NormalizesText;
    use SetsCreatedBy;

    /** @var list<string> */
    protected array $normalizedLabels = ['name', 'subtitle'];

    /** @var list<string> */
    protected array $normalizedProse = ['intro', 'description'];

    /** @var list<string> */
    protected array $normalizedRequired = ['name'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'subtitle',
        'intro',
        'description',
        'active',
        'website',
        'twitter_username',
        'nostr',
        'lightning_address',
        'lnurl',
        'node_id',
        'paynym',
        'team_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'active' => 'boolean',
    ];

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
        $this->addMediaCollection('avatar')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->singleFile()
            ->useFallbackUrl(asset('img/einundzwanzig.png'));
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->useFallbackUrl(asset('img/einundzwanzig.png'));
    }

    /**
     * Get the options for generating the slug.
     */
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
             * Konsistenz mit den uebrigen Models.
             * Ohne diese Zeile erzeugt HasSlug den Slug bei JEDEM Update neu.
             */
            ->doNotGenerateSlugsOnUpdate();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function coursesEvents(): HasManyThrough
    {
        return $this->hasManyThrough(CourseEvent::class, Course::class);
    }

    public function libraryItems(): HasMany
    {
        return $this->hasMany(LibraryItem::class);
    }
}
