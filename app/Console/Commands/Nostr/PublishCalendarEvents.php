<?php

namespace App\Console\Commands\Nostr;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
use App\Support\NostrPayloadFingerprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;

/**
 * NIP-52-Gegenstueck zu {@see PublishUnpublishedItems}:
 * dasselbe Muster (per Cron wiederholt aufgerufen), aber
 * eigene Gating-Spalte (`nostr_coordinate` statt `nostr_status`) und eigener
 * Signierweg (swentel/nostr-php statt `noscl`), siehe Migration
 * 2026_08_29_170000_add_nostr_coordinate... fuer die Begruendung der Trennung.
 *
 * Batch per run, not one record. Until 2026-09-17 each run published a single record,
 * which drains 288 a day; with publishing default-on (migration 2026_09_17_200000) the
 * backlog was 669 upcoming events (measured 2026-09-17) and up to 307 calendars (the
 * meetup count of 2026-09-04). At `--limit=25` the events clear in 27 runs, about
 * 2 h 15 min, and the calendars in 13 runs. The load stays paced: `--sleep` spaces the
 * transmissions, the first rejected send ends the run, and RUN_BUDGET_SECONDS keeps a
 * slow relay from stretching one run across the next ticks.
 */
class PublishCalendarEvents extends Command
{
    protected $signature = 'nostr:publish-calendar
        {--model= : Meetup or MeetupEvent}
        {--limit=25 : Publish at most this many records in one run}
        {--sleep=1 : Seconds to wait between two transmissions}';

    /**
     * No new record is started once a run has been going this long.
     *
     * The batch is what could otherwise turn a slow relay into a burst. The transmitter
     * waits up to 60 s per relay on a silent socket, so a batch of 25 against one
     * hanging relay would run for half an hour — and every five-minute tick in between
     * starts another run that selects the SAME unpublished records, because a
     * coordinate is only written after its send. Stopping short of the next tick keeps
     * that to at most one overlapping run, whatever the relays do. What is left over
     * stays unpublished and is the head of the next run's queue.
     */
    private const RUN_BUDGET_SECONDS = 240;

    protected $description = 'Publish unpublished meetups/events to Nostr as NIP-52 calendar events';

    private bool $hasTransmitted = false;

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
         * leaves the result set. Because this command handles a bounded batch per run
         * (`--limit`, below), the ordering decides which records those are. Until 2026-09-04 it was
         * `created_at DESC`, i.e. newest-created first — an order uncorrelated with
         * the deadline, which put a long-planned event starting tomorrow BEHIND one
         * created this morning for next month. Deadline order makes the loss condition
         * computable instead of arbitrary: an event is only at risk if more events
         * start before it than the schedule can drain within its lead time (at the
         * five-minute cadence in routes/console.php, 288 runs per day — 7200 records
         * at the scheduled batch of 25).
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
            /*
             * `cancelled_at IS NULL` — an event that is off is never published in the
             * first place (issue #141).
             *
             * This is the OTHER half of #141 and it is not the same as the marker. A
             * record already on the relays is repaired by re-sending it with the
             * cancellation marker, because a subscriber has seen it and only something
             * they still receive can tell them otherwise. A record that was never
             * published has no such reader: publishing it now would CREATE a live
             * calendar entry for an event that is not happening — the defect #141
             * reports, arriving through the front door. And it could not be taken back
             * afterwards, since this portal emits no NIP-09 deletion (see
             * RepublishCalendarEvents::records(), which states that gap for the same
             * reason).
             *
             * The ICS feed answers the same question differently, on purpose: it
             * delivers cancelled events for 30 days (MeetupEvent::visibleInCalendarFeed)
             * because it is a full-state feed a client reconciles by UID, where an extra
             * entry is inert. A publish here is a permanent write to public relays.
             *
             * NOT a filter on `nostr_publishing_enabled`'s side of the gate, and not
             * one-way either: an organiser who takes the cancellation back (#140) puts
             * the record straight back into this queue, and it publishes normally as long
             * as its start is still ahead.
             */
            'MeetupEvent' => MeetupEvent::query()
                ->with('meetup.city.country')
                ->whereNull('nostr_coordinate')
                ->whereNull('cancelled_at')
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

        $this->hasTransmitted = false;

        $limit = max(1, (int) $this->option('limit'));
        $records = $query->limit($limit)->get();

        if ($records->isEmpty()) {
            $this->info("No unpublished items for model: {$modelName}");

            return self::SUCCESS;
        }

        $key = new Key;
        $hexKey = str_starts_with($privateKey, 'nsec') ? $key->convertToHex($privateKey) : $privateKey;
        $pubkeyHex = $key->getPublicKey($hexKey);

        $startedAt = now();
        $result = self::SUCCESS;

        /** @var array<int, Meetup> $meetupsWithNewEvents */
        $meetupsWithNewEvents = [];

        foreach ($records as $model) {
            if ($startedAt->diffInSeconds(now()) >= self::RUN_BUDGET_SECONDS) {
                $this->warn(sprintf('Run budget of %d s used up; the remaining records wait for the next run.', self::RUN_BUDGET_SECONDS));

                break;
            }

            /*
             * The first failure ends the run. The records behind it are left exactly as
             * they were — no coordinate, no fingerprint — so the next run picks them up
             * in the same order; and a relay set that rejects one event is not asked to
             * take the next 24 in the same minute.
             */
            if (! $this->publish($model, $modelName, $hexKey, $pubkeyHex)) {
                $result = self::FAILURE;

                break;
            }

            if ($model instanceof MeetupEvent && $model->meetup) {
                $meetupsWithNewEvents[$model->meetup->id] = $model->meetup;
            }
        }

        /*
         * One calendar refresh per meetup, after the batch rather than after every event:
         * a meetup with five new events in this run would otherwise re-send its kind 31924
         * five times in a row, each replacing the last. Done on the failure path too — the
         * events published before the failure are on the relays and belong in the calendar.
         */
        foreach ($meetupsWithNewEvents as $meetup) {
            $this->refreshCalendarFor($meetup, $hexKey);
        }

        return $result;
    }

    /**
     * Sign and transmit one record; on acceptance store where it went and what it was.
     */
    private function publish(Meetup|MeetupEvent $model, string $modelName, string $hexKey, string $pubkeyHex): bool
    {
        $event = match (true) {
            $model instanceof Meetup => NostrCalendarEventFactory::forMeetup($model),
            $model instanceof MeetupEvent => NostrCalendarEventFactory::forMeetupEvent($model, $pubkeyHex),
        };

        $dTag = $model instanceof Meetup
            ? NostrCalendarEventFactory::calendarDTag($model)
            : NostrCalendarEventFactory::eventDTag($model);

        $signer = new Sign;
        $signer->signEvent($event, $hexKey);

        if (! $this->transmit($event)) {
            $this->error("Failed to publish calendar event for {$modelName} #{$model->id}");

            return false;
        }

        $model->nostr_coordinate = NostrCalendarEventFactory::coordinate($event->getKind(), $pubkeyHex, $dTag);
        $model->save();

        /*
         * Record WHAT went out, next to the `nostr_coordinate` that records where it
         * went (issue #92). The coordinate alone cannot answer whether the relays still
         * hold what this portal would publish today, which is the question
         * `nostr:republish-calendar --changed` exists to answer. Written after the
         * transmission succeeded and never before it: a fingerprint stored for an event
         * no relay accepted would declare a record up to date that was never published
         * with that payload at all.
         */
        NostrPayloadFingerprint::remember($model, $event);

        $this->info("Published calendar event for {$modelName} #{$model->id}");

        return true;
    }

    /**
     * Hand one signed event to the relays, pausing `--sleep` seconds before every
     * transmission of this run but the first — calendar refreshes included, since they
     * go to the same relays. A run with a single send pays no wait.
     */
    private function transmit(Event $event): bool
    {
        $sleepSeconds = max(0.0, (float) $this->option('sleep'));

        if ($this->hasTransmitted && $sleepSeconds > 0) {
            usleep((int) round($sleepSeconds * 1_000_000));
        }

        $this->hasTransmitted = true;

        return $this->transmitter->transmit($event, config('services.nostr.relays', []));
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
     *
     * SINCE ISSUE #92 THAT RETRY IS AUTOMATIC. The fingerprint below is only written
     * when the send succeeded, so a warned-about calendar keeps the fingerprint of the
     * payload it last really carried — which no longer matches the one the factory
     * builds now that this event exists. `nostr:republish-calendar --changed` therefore
     * picks it up on its next scheduled run, without waiting for the meetup's next
     * event or for an operator.
     */
    private function refreshCalendarFor(Meetup $meetup, string $hexKey): void
    {
        if ($meetup->nostr_coordinate === null) {
            $this->info("Calendar for Meetup #{$meetup->id} is not published yet — it will include this event when it is.");

            return;
        }

        $calendar = NostrCalendarEventFactory::forMeetup($meetup);

        $signer = new Sign;
        $signer->signEvent($calendar, $hexKey);

        if (! $this->transmit($calendar)) {
            $this->warn("Published the event but could not refresh the calendar for Meetup #{$meetup->id}; it will be retried.");

            return;
        }

        NostrPayloadFingerprint::remember($meetup, $calendar);

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
     * `cancelled_at` is in the list for the same reason and not as an afterthought: it
     * is a column the MeetupEvent query GATES ON since #141, and a degraded
     * `where "cancelled_at" is null` is never true, so its absence would stop every
     * event from publishing while the command reported "No unpublished items" and exit
     * 0. That the column has existed since #56 and the coordinate columns are younger
     * makes the case unlikely, not different — this list says what the query needs, not
     * what history suggests it will find.
     *
     * @return list<string> the missing columns as `table.column`, empty when ready
     */
    private function missingGateColumns(string $modelName): array
    {
        $required = match ($modelName) {
            'Meetup' => ['meetups' => ['nostr_coordinate', 'nostr_publishing_enabled']],
            'MeetupEvent' => ['meetup_events' => ['nostr_coordinate', 'cancelled_at'], 'meetups' => ['nostr_publishing_enabled']],
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
