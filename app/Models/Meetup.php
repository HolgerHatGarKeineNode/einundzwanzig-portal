<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Meetup extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'city_id',
        'intro',
        'telegram_link',
        'webpage',
        'twitter_username',
        'matrix_group',
        'nostr',
        'nostr_status',
        'simplex',
        'signal',
        'community',
        'github_data',
        'visible_on_map',
        'is_active',
        'last_event_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'city_id' => 'integer',
        'github_data' => 'json',
        'simplified_geojson' => 'array',
        'is_active' => 'boolean',
        'last_event_at' => 'datetime',
        'restore_point' => 'array',
    ];

    /**
     * Stammdaten, die im Restore-Point gesichert und wiederhergestellt werden.
     *
     * @var list<string>
     */
    public const RESTORE_POINT_ATTRIBUTES = [
        'name',
        'slug',
        'city_id',
        'intro',
        'telegram_link',
        'webpage',
        'twitter_username',
        'matrix_group',
        'nostr',
        'simplex',
        'signal',
        'community',
        'github_data',
        'visible_on_map',
    ];

    /**
     * Sichert den aktuellen Stand der Stammdaten als Restore-Point.
     * restore_point ist bewusst nicht fillable und nur per Command erreichbar.
     */
    public function captureRestorePoint(): void
    {
        $this->forceFill([
            'restore_point' => [
                'captured_at' => now()->toIso8601String(),
                'attributes' => $this->only(self::RESTORE_POINT_ATTRIBUTES),
            ],
        ])->saveQuietly();
    }

    /**
     * Stellt die Stammdaten aus dem Restore-Point wieder her.
     * Liefert false, wenn noch kein Restore-Point gesichert wurde.
     */
    public function restoreFromRestorePoint(): bool
    {
        $attributes = $this->restore_point['attributes'] ?? null;

        if (! is_array($attributes)) {
            return false;
        }

        $this->forceFill(
            collect($attributes)->only(self::RESTORE_POINT_ATTRIBUTES)->all()
        )->save();

        return true;
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });

        // Der Ersteller wird automatisch als Leiter in die meetup_user-Pivot eingetragen,
        // damit das Meetup einheitlich (MCP, REST-API, Livewire) in „Meine Meetups"
        // erscheint – egal über welchen Pfad es angelegt wurde.
        static::created(function (Meetup $model): void {
            if ($model->created_by !== null) {
                $model->users()->syncWithoutDetaching([
                    $model->created_by => ['is_leader' => true],
                ]);
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
            ->useFallbackUrl(get_domain_attributes()['image']);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Ist der Nutzer über die meetup_user-Pivot Mitglied dieses Meetups?
     */
    public function hasMember(User $user): bool
    {
        return $this->users()->whereKey($user->id)->exists();
    }

    /**
     * Den Nutzer als Mitglied (nicht Leader) zu „Meine Meetups" hinzufügen.
     * Idempotent: ein bereits hinzugefügter Nutzer bleibt unverändert. Gibt
     * true zurück, wenn neu hinzugefügt (false = war bereits Mitglied).
     * Geteilt von REST-Controller (addToMine) und MCP-Tool (AddMeetupToMineTool).
     */
    public function addMember(User $user): bool
    {
        $wasMember = $this->hasMember($user);

        $this->users()->syncWithoutDetaching([
            $user->getKey() => ['is_leader' => false],
        ]);

        return ! $wasMember;
    }

    /**
     * RateLimiter-Key für Meetup-Stammdaten-Updates über das Portal-Frontend.
     */
    public static function updateRateLimitKey(int $userId): string
    {
        return 'meetup-update:'.$userId;
    }

    /**
     * Meetups, die dem Nutzer zugeordnet sind: selbst erstellt (created_by) ODER
     * Mitglied über die meetup_user-Pivot. Entspricht „Meine Meetups" im Portal.
     */
    public function scopeAssociatedWith(Builder $query, int $userId): void
    {
        $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('created_by', $userId)
                ->orWhereHas('users', fn (Builder $user) => $user->whereKey($userId));
        });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    protected function logoSquare(): Attribute
    {
        $media = $this->getFirstMedia('logo');
        if ($media) {
            $path = str($media->getPath())->after('storage/app/');
        } else {
            $path = 'img/einundzwanzig.png';
        }

        return Attribute::make(
            get: fn () => url()->route('img',
                [
                    'path' => $path,
                    'w' => 900,
                    'h' => 900,
                    'fit' => 'crop',
                    'fm' => 'webp',
                ]),
        );
    }

    protected function nextEvent(): Attribute
    {
        $nextEvent = $this->meetupEvents()->where('start', '>=', now())->orderBy('start')->first();

        return Attribute::make(
            get: fn () => $nextEvent ? [
                'id' => $nextEvent->id,
                'start' => $nextEvent->start,
                'portalLink' => url()->route('meetups.landingpage-event',
                    ['country' => $this->city->country, 'meetup' => $this, 'event' => $nextEvent]),
                'location' => $nextEvent->location,
                'description' => $nextEvent->description,
                'link' => $nextEvent->link,
                'attendees' => count($nextEvent->attendees ?? []),
                'might_attendees' => count($nextEvent->might_attendees ?? []),
                'nostr_note' => str($nextEvent->nostr_status)->after('Sent event ')->before(' to '),
            ] : null,
        );
    }

    protected function belongsToMe(): Attribute
    {
        return Attribute::make(
            get: fn () => DB::table('meetup_user')->where('meetup_id', $this->id)->where('user_id',
                auth()->id())->exists(),
        );
    }

    public function meetupEvents(): HasMany
    {
        return $this->hasMany(MeetupEvent::class);
    }

    public function recalculateActivity(): void
    {
        $threshold = now()->subYear();

        $lastEventAt = MeetupEvent::query()
            ->where('meetup_id', $this->id)
            ->where('start', '<=', now())
            ->max('start');

        $lastEventAt = $lastEventAt ? Date::parse($lastEventAt) : null;

        $hasFutureEvent = MeetupEvent::query()
            ->where('meetup_id', $this->id)
            ->where('start', '>', now())
            ->exists();

        $hasActiveRecurrence = MeetupEvent::query()
            ->where('meetup_id', $this->id)
            ->whereNotNull('recurrence_type')
            ->whereNotNull('recurrence_end_date')
            ->where('recurrence_end_date', '>=', now())
            ->exists();

        $isActive = ($lastEventAt && $lastEventAt->greaterThanOrEqualTo($threshold))
            || $hasFutureEvent
            || $hasActiveRecurrence;

        $this->forceFill([
            'is_active' => $isActive,
            'last_event_at' => $lastEventAt,
        ])->saveQuietly();
    }
}
