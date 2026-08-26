<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use App\Enums\RsvpStatus;
use App\Models\Concerns\NormalizesText;
use App\Models\Concerns\SetsCreatedBy;
use App\Observers\ApiChangeObserver;
use App\Observers\MeetupEventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\Tags\HasTags;

#[ObservedBy([MeetupEventObserver::class, ApiChangeObserver::class])]
class MeetupEvent extends Model
{
    use HasFactory;
    use HasTags;
    use NormalizesText;
    use SetsCreatedBy;

    /** @var list<string> */
    protected array $normalizedLabels = ['title', 'location'];

    /** @var list<string> */
    protected array $normalizedProse = ['description'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'meetup_id' => 'integer',
        'start' => 'datetime',
        'osm_id' => 'integer',
        'osm_lat' => 'decimal:7',
        'osm_lon' => 'decimal:7',
        // End of THIS event — not to be confused with recurrence_end_date, which is
        // when a recurring series stops producing occurrences.
        'end' => 'datetime',
        'recurrence_end_date' => 'datetime',
        /*
         * Dieser Eintrag stand bis P6 in einer Eigenschaft `$enumCasts` — die es in
         * Eloquent NICHT gibt. Der Cast lief damit ins Leere und `recurrence_type` kam
         * als roher String zurueck.
         *
         * Das war nicht folgenlos, wie zunaechst angenommen, sondern ein 500er im
         * Betrieb: `meetups.create-edit-events` haelt die Auswahl in
         * `public ?RecurrenceType $recurrenceType` und weist ihr in `mount()` direkt
         * `$this->event->recurrence_type` zu. Kommt das Model aus der Datenbank (also
         * ueber Route-Model-Binding, also immer im echten Betrieb), ist das ein String,
         * und die Zuweisung wirft `Cannot assign string to property ... of type
         * ?App\Enums\RecurrenceType`. Jedes Bearbeiten eines bestehenden SERIEN-Termins
         * lief in diesen Fehler.
         *
         * Warum kein Test das sah: alle bestehenden Tests reichen der Komponente ein
         * frisch erzeugtes Model, dessen Attribut noch das Enum-Objekt aus dem
         * `create()`-Aufruf haelt. Erst ein `find()` aus der Datenbank stellt den
         * echten Zustand her — die Messung traf den Fall nie, nicht der Code den Test.
         *
         * Die Ausgabe aendert sich dadurch NICHT: ein Backed Enum serialisiert in JSON zu
         * seinem Wert, `recurrence_type` bleibt also `"weekly"` in der API.
         */
        'recurrence_type' => RecurrenceType::class,
        'attendees' => 'array',
        'might_attendees' => 'array',
    ];

    /**
     * Termine, die der Nutzer bearbeiten darf: selbst angelegt ODER Leader des
     * zugehörigen Meetups (deckungsgleich mit MeetupEventPolicy::update).
     */
    public function scopeEditableBy(Builder $query, int $userId): void
    {
        $query->where(function (Builder $query) use ($userId) {
            $query->where('created_by', $userId)
                ->orWhereHas('meetup', fn (Builder $meetup) => $meetup->ledBy($userId));
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function meetup(): BelongsTo
    {
        return $this->belongsTo(Meetup::class);
    }

    /**
     * Anzahl der Zusagen bzw. Vielleicht-Sagen (die Listen sind JSON-Arrays).
     */
    public function attendeesCount(): int
    {
        return count($this->attendees ?? []);
    }

    public function mightAttendeesCount(): int
    {
        return count($this->might_attendees ?? []);
    }

    /**
     * Eindeutige Kennung eines angemeldeten Nutzers in den Teilnehmer-Listen.
     * Einträge werden als `id_<userId>|<name>` abgelegt; der angehängte Pipe
     * grenzt z. B. `id_5` sauber von `id_50` ab.
     */
    public static function rsvpIdentifierFor(User $user): string
    {
        return 'id_'.$user->id;
    }

    /**
     * Prefix, mit dem ein Eintrag des Nutzers in den Listen beginnt — inklusive
     * Pipe, damit `id_5` nicht auf `id_50` matcht.
     */
    private static function rsvpPrefixFor(User $user): string
    {
        return self::rsvpIdentifierFor($user).'|';
    }

    /**
     * Aktueller RSVP-Status des Nutzers für diesen Termin.
     */
    public function rsvpStatusFor(User $user): RsvpStatus
    {
        $prefix = self::rsvpPrefixFor($user);

        if (collect($this->attendees ?? [])->contains(fn ($entry): bool => str($entry)->startsWith($prefix))) {
            return RsvpStatus::Attending;
        }

        if (collect($this->might_attendees ?? [])->contains(fn ($entry): bool => str($entry)->startsWith($prefix))) {
            return RsvpStatus::Maybe;
        }

        return RsvpStatus::None;
    }

    /**
     * Setzt den RSVP-Status des Nutzers: entfernt ihn zunächst aus beiden Listen
     * und trägt ihn anschließend in die gewählte Liste ein. `None` sagt nur ab
     * (kein erneutes Eintragen). Persistiert die Änderung.
     */
    public function setRsvpFor(User $user, RsvpStatus $status, string $name): void
    {
        $prefix = self::rsvpPrefixFor($user);

        $attendees = $this->withoutEntry($this->attendees, $prefix);
        $mightAttendees = $this->withoutEntry($this->might_attendees, $prefix);

        $entry = $prefix.$name;

        match ($status) {
            RsvpStatus::Attending => $attendees->push($entry),
            RsvpStatus::Maybe => $mightAttendees->push($entry),
            RsvpStatus::None => null,
        };

        $this->update([
            'attendees' => $attendees->values()->all(),
            'might_attendees' => $mightAttendees->values()->all(),
        ]);
    }

    /**
     * @param  array<int, string>|null  $list
     * @return Collection<int, string>
     */
    private function withoutEntry(?array $list, string $prefix): Collection
    {
        return collect($list ?? [])->reject(fn ($entry): bool => str($entry)->startsWith($prefix));
    }
}
