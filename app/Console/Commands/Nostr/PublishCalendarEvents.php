<?php

namespace App\Console\Commands\Nostr;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;

/**
 * NIP-52-Gegenstueck zu {@see PublishUnpublishedItems}:
 * dasselbe Muster (ein Datensatz pro Lauf, per Cron wiederholt aufgerufen), aber
 * eigene Gating-Spalte (`nostr_coordinate` statt `nostr_status`) und eigener
 * Signierweg (swentel/nostr-php statt `noscl`), siehe Migration
 * 2026_08_29_170000_add_nostr_coordinate... fuer die Begruendung der Trennung.
 */
class PublishCalendarEvents extends Command
{
    protected $signature = 'nostr:publish-calendar {--model=}';

    protected $description = 'Publish unpublished meetups/events to Nostr as NIP-52 calendar events';

    public function __construct(private readonly NostrEventTransmitter $transmitter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $privateKey = config('services.nostr.publisher_key');

        if (! $privateKey) {
            $this->error('NOSTR_PUBLISHER_NSEC ist nicht gesetzt.');

            return self::FAILURE;
        }

        $modelName = $this->option('model');

        /*
         * The two orderings are deliberately DIFFERENT, because only one of the two
         * queries has a deadline.
         *
         * MeetupEvent — `orderBy('start')`, and this one is correctness, not tuning.
         * The query is gated on `start > now()`, so an event that does not reach the
         * front of the queue before it begins is never published AT ALL; it silently
         * leaves the result set. Because this command handles one record per run
         * (below), the ordering decides which record that is. Until 2026-09-04 it was
         * `created_at DESC`, i.e. newest-created first — an order uncorrelated with
         * the deadline, which put a long-planned event starting tomorrow BEHIND one
         * created this morning for next month. Deadline order makes the loss condition
         * computable instead of arbitrary: an event is only at risk if more events
         * start before it than the schedule can drain within its lead time (at the
         * five-minute cadence in routes/console.php, 288 per day).
         *
         * Meetup — `orderBy('created_at')`, ascending. A calendar has no `start` and
         * this query has no time gate, so no ordering here can lose a record; the
         * choice is about starvation, not loss. `created_at DESC` has no bounded
         * worst-case wait: every newly created meetup that opts in inserts itself
         * AHEAD of an older one still waiting, so a long-standing meetup's position
         * can get worse forever. Ascending is a plain FIFO — a record's position only
         * ever improves. Not ascending for symmetry with the query above: the field
         * differs because the reason differs, and `start` would be meaningless here.
         */
        $query = match ($modelName) {
            'Meetup' => Meetup::query()
                ->with('city.country')
                ->whereNull('nostr_coordinate')
                ->where('nostr_publishing_enabled', true)
                ->orderBy('created_at'),
            'MeetupEvent' => MeetupEvent::query()
                ->with('meetup.city.country')
                ->whereNull('nostr_coordinate')
                ->where('start', '>', now())
                ->whereHas('meetup', fn ($meetup) => $meetup->where('nostr_publishing_enabled', true))
                ->orderBy('start'),
            default => null,
        };

        if (! $query) {
            $this->error("Unsupported model: {$modelName}");

            return self::FAILURE;
        }

        $missingColumns = $this->missingGateColumns($modelName);

        if ($missingColumns !== []) {
            $this->error(sprintf(
                'Missing column(s): %s — run php artisan migrate. Without them this command finds nothing to publish and would exit 0 as if everything were up to date.',
                implode(', ', $missingColumns),
            ));

            return self::FAILURE;
        }

        $model = $query->first();

        if (! $model) {
            $this->info("No unpublished items for model: {$modelName}");

            return self::SUCCESS;
        }

        $key = new Key;
        $hexKey = str_starts_with($privateKey, 'nsec') ? $key->convertToHex($privateKey) : $privateKey;
        $pubkeyHex = $key->getPublicKey($hexKey);

        $event = match (true) {
            $model instanceof Meetup => NostrCalendarEventFactory::forMeetup($model),
            $model instanceof MeetupEvent => NostrCalendarEventFactory::forMeetupEvent($model, $pubkeyHex),
        };

        $dTag = $model instanceof Meetup
            ? NostrCalendarEventFactory::calendarDTag($model)
            : NostrCalendarEventFactory::eventDTag($model);

        $signer = new Sign;
        $signer->signEvent($event, $hexKey);

        $accepted = $this->transmitter->transmit($event, config('services.nostr.relays', []));

        if (! $accepted) {
            $this->error("Failed to publish calendar event for {$modelName} #{$model->id}");

            return self::FAILURE;
        }

        $model->nostr_coordinate = NostrCalendarEventFactory::coordinate($event->getKind(), $pubkeyHex, $dTag);
        $model->save();

        $this->info("Published calendar event for {$modelName} #{$model->id}");

        if ($model instanceof MeetupEvent) {
            $this->refreshCalendarFor($model, $hexKey);
        }

        return self::SUCCESS;
    }

    /**
     * Re-send the meetup's kind 31924 calendar so that it lists the event just
     * published (issue #104).
     *
     * WHY THIS IS NEEDED AT ALL. `NostrCalendarEventFactory::forMeetup()` builds the
     * `a` tags from the events that already carry an `nostr_coordinate`, so a calendar
     * is only ever right at the moment it is built. This command's Meetup arm is gated
     * on `nostr_coordinate IS NULL` and never re-sends, so a calendar published before
     * its events would stay empty for good — which is exactly what the reporter of #104
     * saw. Kind 31924 is parameterized-replaceable, so re-sending under the same `d`
     * tag with a newer `created_at` replaces it in place on every conforming relay.
     *
     * The order runs both ways round without a special case: an event published BEFORE
     * its calendar is picked up when the Meetup arm finally builds that calendar, and
     * an event published AFTER it is picked up here. `$model->save()` above has already
     * committed the new coordinate, so the query behind the `a` tags sees it.
     *
     * A FAILED CALENDAR SEND DOES NOT FAIL THE RUN. The event itself is published and
     * its coordinate is stored; reporting failure now would tell the scheduler that a
     * completed piece of work did not happen, and there is no way to un-publish the
     * event to make that true. The calendar is retried by the next event of the same
     * meetup and by `nostr:republish-calendar`, so the damage of a warning is bounded
     * to a stale calendar, while the damage of a false failure is an operator chasing
     * a publish that succeeded.
     */
    private function refreshCalendarFor(MeetupEvent $meetupEvent, string $hexKey): void
    {
        $meetup = $meetupEvent->meetup;

        if (! $meetup) {
            return;
        }

        if ($meetup->nostr_coordinate === null) {
            $this->info("Calendar for Meetup #{$meetup->id} is not published yet — it will include this event when it is.");

            return;
        }

        $calendar = NostrCalendarEventFactory::forMeetup($meetup);

        $signer = new Sign;
        $signer->signEvent($calendar, $hexKey);

        if (! $this->transmitter->transmit($calendar, config('services.nostr.relays', []))) {
            $this->warn("Published the event but could not refresh the calendar for Meetup #{$meetup->id}; it will be retried.");

            return;
        }

        $this->info("Refreshed calendar for Meetup #{$meetup->id}");
    }

    /**
     * The columns the query above gates on, per model — reported, never assumed.
     *
     * Issue #72: SQLite degrades a double-quoted identifier that matches no column
     * into a STRING LITERAL instead of raising an error, and Laravel quotes
     * identifiers with double quotes. On a database where the 2026_08_29 migrations
     * have not run, `where "nostr_coordinate" is null` therefore compares the constant
     * string 'nostr_coordinate' against NULL — never true. This command would find
     * nothing to publish, print "No unpublished items" and exit 0, which an operator
     * reading exit codes cannot tell apart from a healthy, caught-up system. The list
     * is written out rather than derived so that it stays readable next to the queries
     * it mirrors; `NostrWhoami::publishingState()` contains the same check.
     *
     * @return list<string> the missing columns as `table.column`, empty when ready
     */
    private function missingGateColumns(string $modelName): array
    {
        $required = match ($modelName) {
            'Meetup' => ['meetups' => ['nostr_coordinate', 'nostr_publishing_enabled']],
            'MeetupEvent' => ['meetup_events' => ['nostr_coordinate'], 'meetups' => ['nostr_publishing_enabled']],
            default => [],
        };

        $missing = [];

        foreach ($required as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        return $missing;
    }
}
