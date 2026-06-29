<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use App\Enums\RsvpStatus;
use App\Models\Concerns\SetsCreatedBy;
use App\Observers\MeetupEventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[ObservedBy([MeetupEventObserver::class])]
class MeetupEvent extends Model
{
    use HasFactory;
    use SetsCreatedBy;

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
        'recurrence_end_date' => 'datetime',
        'attendees' => 'array',
        'might_attendees' => 'array',
    ];

    /**
     * The attributes that should be cast to enums.
     *
     * @var array
     */
    protected $enumCasts = [
        'recurrence_type' => RecurrenceType::class,
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
