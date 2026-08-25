<?php

namespace App\Models;

use App\Models\Concerns\SetsCreatedBy;
use App\Observers\ApiChangeObserver;
use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[ObservedBy([ApiChangeObserver::class])]
class Meetup extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;
    use SetsCreatedBy;

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
        'rsvp_enabled',
        'attendees_public',
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
        'rsvp_enabled' => 'boolean',
        'attendees_public' => 'boolean',
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
        'rsvp_enabled',
        'attendees_public',
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
            /*
             * Feste Sprache statt Cookie::get('lang'): die Transliteration haengt sonst
             * am Nutzer, der gerade speichert — Str::slug macht aus "Koeln" nur im
             * deutschen Modus "koeln", sonst "koln". Gemessen am 2026-08-22: 37 der 314
             * Meetups haetten sich allein dadurch verschoben. Ausserdem greift Cookie::get()
             * in Konsolenbefehlen und Jobs ins Leere.
             */
            ->usingLanguage(config('app.locale'))
            /*
             * Der Slug ist Route-Key (/{country}/meetup/{meetup:slug}), er darf sich nie mehr aendern.
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
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'])
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
     * Ist der Nutzer Leader dieses Meetups (meetup_user.is_leader = true)?
     * Nur Leader (und der Ersteller/Super-Admin) dürfen Stammdaten bearbeiten
     * und weitere Leader einsetzen/entziehen.
     */
    public function isLeader(User $user): bool
    {
        return $this->users()
            ->whereKey($user->id)
            ->wherePivot('is_leader', true)
            ->exists();
    }

    /**
     * Darf der (ggf. anonyme) Betrachter die Teilnehmerliste/Zähler dieses Meetups
     * sehen? Öffentlich, wenn attendees_public gesetzt ist – sonst nur für die, die
     * das Meetup verwalten dürfen (Ersteller/Super-Admin/Leader, = update-Ability).
     */
    public function attendeesVisibleTo(?User $user): bool
    {
        return $this->attendees_public || ($user !== null && $user->can('update', $this));
    }

    /**
     * Aktuelle Leader dieses Meetups (meetup_user.is_leader = true), Ersteller
     * zuerst. Gemeinsame Quelle für REST-API (MeetupLeaderController) und das
     * Portal-Frontend (meetups.edit).
     *
     * @return Collection<int, User>
     */
    public function leaders(): Collection
    {
        return $this->users()
            ->wherePivot('is_leader', true)
            ->get()
            ->sortByDesc(fn (User $user): bool => $user->getKey() === $this->created_by)
            ->values();
    }

    /**
     * Nutzer zum Leader befördern (meetup_user.is_leader = true). Idempotent.
     * Geteilt von REST-Controller, MCP-Tools und Portal-Frontend.
     */
    public function promoteLeader(User $user): void
    {
        $this->users()->syncWithoutDetaching([$user->getKey() => ['is_leader' => true]]);
    }

    /**
     * Nutzer die Leader-Rolle entziehen (Demote; bleibt Mitglied). Der Ersteller
     * des Meetups ist geschützt und wird nie entzogen (Domain-Invariante) —
     * Aufrufer dürfen zusätzlich mit 403 antworten.
     */
    public function demoteLeader(User $user): void
    {
        if ($user->getKey() === $this->created_by) {
            return;
        }

        $this->users()->updateExistingPivot($user->getKey(), ['is_leader' => false]);
    }

    /**
     * Den Nutzer als Mitglied (nicht Leader) zu „Meine Meetups" hinzufügen.
     * Idempotent: ein bereits hinzugefügter Nutzer bleibt unverändert. Gibt
     * true zurück, wenn neu hinzugefügt (false = war bereits Mitglied).
     * Geteilt von REST-Controller (addToMine) und MCP-Tool (AddMeetupToMineTool).
     *
     * Bewusst attach() statt syncWithoutDetaching([...  => ['is_leader' => false]]):
     * sync() schreibt übergebene Pivot-Attribute per updateExistingPivot auch auf
     * BESTEHENDE Zeilen. Ein Leader, der sein eigenes Meetup nochmals zu „Meine
     * Meetups" hinzufügt, wurde dadurch still auf is_leader = false degradiert und
     * verlor Schreibrechte auf Meetup und alle Termine (403 beim Termin-Anlegen).
     */
    public function addMember(User $user): bool
    {
        if ($this->hasMember($user)) {
            return false;
        }

        $this->users()->attach($user->getKey(), ['is_leader' => false]);

        return true;
    }

    /**
     * Den Nutzer wieder aus „Meine Meetups" entfernen (löst die meetup_user-Pivot).
     * Idempotent: war der Nutzer kein Mitglied, passiert nichts. Gibt true zurück,
     * wenn tatsächlich eine Zuordnung gelöst wurde (false = war kein Mitglied).
     * Gegenstück zu {@see addMember()}; die Stammdaten bleiben unberührt.
     */
    public function removeMember(User $user): bool
    {
        return $this->users()->detach($user->getKey()) > 0;
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

    /**
     * Meetups, die der Nutzer als Leader führt (meetup_user.is_leader = true).
     * Maßgeblich dafür, wer Stammdaten UND Termine bearbeiten darf.
     */
    public function scopeLedBy(Builder $query, int $userId): void
    {
        $query->whereHas('users', fn (Builder $user) => $user->whereKey($userId)->where('meetup_user.is_leader', true));
    }

    /**
     * Führt der eingeloggte Nutzer dieses Meetup als Leader? Steuert die
     * Sichtbarkeit der Bearbeiten-/Termin-Affordances im Portal-Frontend
     * (Gegenstück zu {@see belongsToMe()}, aber leader- statt mitgliedschafts-
     * basiert).
     */
    protected function leadByMe(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => auth()->check() && DB::table('meetup_user')
                ->where('meetup_id', $this->id)
                ->where('user_id', auth()->id())
                ->where('is_leader', true)
                ->exists()
        );
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
                // Der praezise Kartenort, wenn einer gewaehlt wurde — das Popup zeigt
                // ihn ueber dem Freitext, statt ihn zu ersetzen.
                'osm_name' => $nextEvent->osm_name,
                'osm_type' => $nextEvent->osm_type,
                'osm_id' => $nextEvent->osm_id,
                'osm_address' => $nextEvent->osm_address,
                'description' => $nextEvent->description,
                'link' => $nextEvent->link,
                // null = Teilnehmerzahl öffentlich verborgen (attendees_public=false).
                'attendees' => $this->attendees_public ? $nextEvent->attendeesCount() : null,
                'might_attendees' => $this->attendees_public ? $nextEvent->mightAttendeesCount() : null,
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

        /*
         * Die dritte Bedingung bleibt bewusst stehen (P5, 2026-08-25).
         *
         * Sie galt als toter Zweig: die fuenf `recurrence_*`-Spalten wurden angeblich
         * nie geschrieben. Das stimmt nur fuer den Livewire-Serienpfad. Ueber
         * `POST /api/meetup-events` (Einzelpfad, {@see MeetupEventController::store})
         * und ueber `PATCH` sind beide Felder seit jeher setzbar, und vier Tests in
         * `tests/Feature/Database/UpdateMeetupActivityTest.php` legen das Verhalten
         * ausdruecklich fest. Der Zweig ist also kein Versehen, sondern Vertrag.
         *
         * Seit P5 schreibt {@see CreateMeetupEventSeries} die Felder auf jedes
         * Vorkommen. Das macht den Zweig haeufiger erfuellbar, aber nicht gefaehrlich:
         * eine frisch angelegte Serie mit Enddatum in der Zukunft hat per Konstruktion
         * auch kuenftige Vorkommen — `$hasFutureEvent` traegt denselben Fall bereits.
         * Ein Massenumschlag auf Bestandsdaten ist ausgeschlossen, weil die
         * Gruppierungs-Migration ausschliesslich `recurrence_group` fuellt und keine
         * einzige `recurrence_*`-Spalte rueckwirkend setzt.
         *
         * Bekannte Restluecke, nicht in P5 geloest: schneidet
         * {@see ExpandRecurrenceSeries::MAX_OCCURRENCES} eine lange Serie ab, steht ein
         * Enddatum in der Zukunft, zu dem irgendwann kein Vorkommen mehr existiert —
         * dann haelt allein dieser Zweig das Meetup aktiv.
         */
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
        ]);

        /*
         * Der Aktivitaetswechsel muss von Hand gemeldet werden (Issue #29).
         *
         * `saveQuietly()` unterdrueckt jedes Model-Event, also auch das `updated`, an
         * dem der ApiChangeObserver haengt. `is_active` und `last_event_at` stehen aber
         * beide im MeetupResource und aendern sich naechtlich fuer viele Meetups —
         * ohne diesen Aufruf veraltet jeder Listen-Cache still an genau diesen zwei
         * Feldern, ohne Fehler und ohne dass es jemandem auffiele.
         *
         * `isDirty()` wird VOR dem Speichern gefragt: danach sind die neuen Werte die
         * originalen und der Vergleich waere immer falsch. Damit erzeugt ein Lauf, der
         * nichts aendert — der Normalfall des naechtlichen Commands ueber alle Meetups —
         * auch keine Zeile.
         */
        $activityChanged = $this->isDirty(['is_active', 'last_event_at']);

        $this->saveQuietly();

        if ($activityChanged) {
            ChangeRecorder::record($this, 'updated');
        }
    }
}
