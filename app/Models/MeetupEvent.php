<?php

namespace App\Models;

use App\Actions\MeetupEvents\CreateMeetupEventSeries;
use App\Enums\RecurrenceType;
use App\Enums\RsvpStatus;
use App\Http\Requests\Api\UpdateMeetupEventRequest;
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

    /**
     * How many links one event may carry (issue #70).
     *
     * The number the reporter asked for ("allow three to five values"), taken at its
     * upper end. It is a validation limit, not a truncation: a sixth entry is refused
     * with a message wherever it is submitted, and {@see self::normaliseLinks()}
     * deliberately does NOT cap the list — a model that silently threw the sixth link
     * away would turn a rejected request into a half-accepted one.
     */
    public const MAX_LINKS = 5;

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
        // The event's links (issue #70), a list of ['url' => …, 'label' => …] entries.
        // NULL and [] are not the same thing here — see self::linkList().
        'links' => 'array',
    ];

    /**
     * Keeps `links` and the deprecated `link` column saying the same thing (issue #70).
     *
     * A model hook rather than something in the write paths, for the reason
     * {@see NormalizesText} gives one door up: an event is created
     * and changed through FOUR of them — the Livewire editor, the REST API, the MCP
     * tools and {@see CreateMeetupEventSeries} — and only two
     * of them know about `links` at all. The other two write `link`, and their rows must
     * still come out with a usable list.
     *
     * The precedence when BOTH are dirty in one save is `links`: it is the richer field
     * and the one this issue introduced, so a payload carrying both is taken to mean the
     * list, and `link` is overwritten with its first URL rather than fighting it.
     *
     * With ONE exception, and it is the reason `null` and `[]` mean different things on
     * this attribute: a `links` of null is "not given", not "no links". Otherwise a
     * writer that fills every column it knows about would wipe the legacy field it just
     * set — {@see CreateMeetupEventSeries} does exactly that,
     * writing `links => null` alongside a `link` for a series created through the old
     * API field, and every occurrence would have come out linkless. Removing every link
     * is `[]`, which is what the editor and the API document.
     *
     * ## Why the null is undone HERE and not in the API request
     *
     * The first version of this hook only declined to DERIVE from a null; the null itself
     * still reached the column, and `PATCH {"links": null}` therefore blanked a stored
     * list — five labelled entries collapsed to the single URL the `link` mirror holds,
     * silently and irreversibly. Laravel's `sometimes` does not help: an explicitly sent
     * null counts as PRESENT, so the key is validated, survives into validated() and
     * lands in update().
     *
     * Stripping the key in {@see UpdateMeetupEventRequest} would
     * have fixed that one caller and left the trap armed for the other three: the MCP
     * tools, the editor, and any internal `$event->update(['links' => null])` — which is
     * the exact shape a writer produces when it maps a nullable DTO onto columns. The
     * rule is a property of the ATTRIBUTE ("null means: I am not talking about the
     * links"), so it belongs where the attribute is, next to the mirror it protects.
     *
     * The raw original is put back rather than the decoded one: assigning through the
     * cast would re-encode the JSON, and "unchanged" has to mean the bytes in the column,
     * not a value that merely decodes the same. Restoring the raw string also makes the
     * attribute clean again, so the UPDATE statement does not carry the column at all.
     *
     * ## Writing `link` moves ONE entry, not the whole list (issue #108)
     *
     * The last branch used to build the list from the deprecated field alone, so
     * `PATCH {"link": null}` left an event that had five labelled links with none —
     * the same harm #70 closed one field over, and not hypothetical: the MCP update
     * tool exposed `link` and nothing else, so an agent that did not re-send a URL
     * wiped a list it had no way of knowing about.
     *
     * `link` is a view of ENTRY ONE, so writing it replaces entry one and clearing it
     * removes entry one; entries two to five keep their order and their labels either
     * way. The replacement carries no label, because a label describes the URL it was
     * written for and not the one that took its place. On an empty list there is no
     * entry one, so clearing is a no-op; on a one-entry list — every pre-#70 row and
     * every event a legacy client ever created — entry one IS the list, so the field
     * behaves exactly as it always did. Replacing the WHOLE list is what `links` is
     * for, and the two must not be conflated.
     *
     * The mirror is re-derived afterwards rather than left at what the caller sent:
     * `link` is documented as the first of `links`, and a null next to four surviving
     * entries would tell a client that reads only the legacy field that this event has
     * no link at all.
     */
    protected static function booted(): void
    {
        static::saving(function (self $meetupEvent): void {
            if ($meetupEvent->isDirty('links') && $meetupEvent->links === null) {
                $meetupEvent->attributes['links'] = $meetupEvent->getRawOriginal('links');
            }

            if ($meetupEvent->isDirty('links') && $meetupEvent->links !== null) {
                $normalised = self::normaliseLinks($meetupEvent->links);

                $meetupEvent->setAttribute('links', $normalised);
                $meetupEvent->setAttribute('link', $normalised[0]['url'] ?? null);

                return;
            }

            if ($meetupEvent->isDirty('link')) {
                $entries = self::withFirstLink($meetupEvent->links, $meetupEvent->link);

                $meetupEvent->setAttribute('links', $entries);
                $meetupEvent->setAttribute('link', $entries[0]['url'] ?? null);
            }
        });
    }

    /**
     * The list that results from writing the deprecated `link` on it (issue #108).
     *
     * Entry one is replaced by the given URL, or removed when there is none — a blank
     * string counts as none, because a URL that is only whitespace is not a link. The
     * splice does both in one step: with an empty replacement it deletes, with a
     * one-entry replacement it substitutes, and on an empty list it appends the only
     * entry there can be.
     *
     * A NULL `links` is the pre-#70 row {@see self::linkList()} describes. Its list is
     * whatever the deprecated column held, which is entry one and nothing else — so
     * treating it as empty here and writing the result back is the same answer, and it
     * leaves the row in the one shape the column is supposed to have.
     *
     * @param  mixed  $links  The list as it stands, in any shape a writer may have left.
     * @return list<array{url: string, label?: string}>
     */
    private static function withFirstLink(mixed $links, ?string $url): array
    {
        $entries = self::normaliseLinks($links);

        array_splice($entries, 0, 1, self::normaliseLinks([$url]));

        return $entries;
    }

    /**
     * The links of this event, in the organiser's order, in ONE shape (issue #70).
     *
     * Every entry has a `url` and a `label`, and the label is null when there is none —
     * so a consumer never has to ask whether the key is there. That is the counterpart
     * of the storage rule in {@see self::normaliseLinks()}, which omits an empty label
     * instead of storing `"label": ""`.
     *
     * A NULL `links` column falls back to the deprecated `link`. NULL means "never
     * written in the new shape" — a row the backfill did not reach, or one a writer
     * outside the model produced — while an empty array means the organiser removed
     * every link. Without this distinction the fallback would resurrect links somebody
     * deleted on purpose.
     *
     * @return list<array{url: string, label: string|null}>
     */
    public function linkList(): array
    {
        $links = $this->links ?? ($this->link === null ? [] : [$this->link]);

        return array_map(
            fn (array $entry): array => ['url' => $entry['url'], 'label' => $entry['label'] ?? null],
            self::normaliseLinks($links),
        );
    }

    /**
     * The stored form of a link list: trimmed, without blank entries, without empty
     * labels (issue #70).
     *
     * Accepts what the four write paths actually hand over — a list of arrays from the
     * API and the editor, a bare string from a `link`-only writer — and answers with the
     * one shape the column holds.
     *
     * An entry whose URL is blank is NOT an entry and disappears, label or no label:
     * the URL is the link, the label only says what it is. A label that is blank or
     * whitespace disappears from its entry, so the column never carries `"label": ""` —
     * two spellings of "no label" would mean every reader has to handle both.
     *
     * @param  mixed  $links  Anything a writer may have put on the attribute.
     * @return list<array{url: string, label?: string}>
     */
    public static function normaliseLinks(mixed $links): array
    {
        return collect(is_array($links) ? $links : [])
            ->map(function ($entry): array {
                $url = trim((string) (is_array($entry) ? ($entry['url'] ?? '') : $entry));
                $label = trim((string) (is_array($entry) ? ($entry['label'] ?? '') : ''));

                return $label === '' ? ['url' => $url] : ['url' => $url, 'label' => $label];
            })
            ->filter(fn (array $entry): bool => $entry['url'] !== '')
            ->values()
            ->all();
    }

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
