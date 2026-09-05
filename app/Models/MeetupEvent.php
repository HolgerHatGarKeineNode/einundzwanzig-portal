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

    /**
     * How long a cancelled event stays in the calendar feed, counted from its
     * own start (issue #56).
     *
     * A cancellation has to outlive the moment it is entered: a subscriber's
     * client only learns about it on its next fetch, and the interval between
     * fetches is the client's business, not ours (Google, Apple and Outlook all
     * differ). Keeping every cancellation forever is the other extreme — the
     * feed would grow without limit and every subscriber would pay for it on
     * every fetch. Thirty days after the start is the window the repo owner
     * settled on: it covers even a client that syncs monthly, and once the date
     * itself is a month past, an entry nobody attended has nothing left to say.
     */
    public const CANCELLED_FEED_WINDOW_DAYS = 30;

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
        // When the organiser called this event off. Null means it is on. Not the
        // same as deleting the row — see MeetupEvent::CANCELLED_FEED_WINDOW_DAYS.
        'cancelled_at' => 'datetime',
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

    /**
     * Was this event called off?
     *
     * Cancelling and deleting are two different operations from #56 on, and the
     * organiser form offers both:
     *
     * - CANCEL — the event was announced and is not happening. The row stays, the
     *   calendar feed reports STATUS:CANCELLED for it (see DownloadMeetupCalendar),
     *   and after CANCELLED_FEED_WINDOW_DAYS it leaves the feed on its own.
     * - DELETE — the event should never have been there: a duplicate, a typo, a
     *   wrong meetup. The row goes, and with it every trace. A subscriber is told
     *   nothing, which is acceptable for an entry that was a mistake and is
     *   exactly what this operation has always done.
     *
     * A cancelled event stays VISIBLE on the website, unchanged. That is the
     * smaller change and the one the organiser means: the visitor who has the
     * date in their head looks the meetup up to check, and an entry that silently
     * vanished from the page answers that question with the same silence the
     * calendar feed used to. Making the site MARK the entry as cancelled is worth
     * doing and is not in this issue — it needs the public event views, which #56
     * does not touch.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * The events a calendar subscriber must see (issue #56).
     *
     * Two disjoint sets, not one date range: an event that is still on belongs in
     * the feed while it is still ahead (unchanged — this is the `start >= now()`
     * the feed has always used), and a cancelled one belongs in it until
     * CANCELLED_FEED_WINDOW_DAYS after its start, because the client that already
     * materialised the entry needs to be told about the cancellation, and only a
     * VEVENT it still receives can tell it. A cancelled event whose start is
     * already past therefore stays for a while, which is exactly the point; a
     * non-cancelled one does not, which keeps the existing feed unchanged.
     *
     * Both halves are wrapped in one group explicitly, and the honest note about
     * that: it is belt-and-braces, not the thing that holds. Eloquent's
     * callScope() already wraps whatever wheres a scope adds
     * (addNewWheresWithinGroup), so the `orWhere` cannot escape into the caller's
     * WHERE and OR past the country filter — measured by deleting the wrapper,
     * which left all eight #56 tests green. It stays because the guarantee then
     * lives in the query builder rather than in this file: the day someone calls
     * this body from something that is not a scope, the group is what keeps a
     * one-country subscription from shipping the whole world (the fail-open shape
     * #78 was about).
     */
    public function scopeVisibleInCalendarFeed(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query
                ->where(fn (Builder $query) => $query
                    ->whereNull('cancelled_at')
                    ->where('start', '>=', now()))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNotNull('cancelled_at')
                    ->where('start', '>=', now()->subDays(self::CANCELLED_FEED_WINDOW_DAYS)));
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
