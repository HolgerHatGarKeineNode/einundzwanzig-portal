<?php

namespace App\Console\Commands\Nostr;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
use App\Support\NostrPayloadFingerprint;
use App\Support\NostrPublishFailures;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
 *
 * ENDING THE RUN AT THE FIRST REJECTION IS BOUNDED, and that bound is
 * {@see NostrPublishFailures}. Without it the two paragraphs above work against each
 * other: a payload no relay will accept sits at the head of an ordered queue and ends
 * every run at the same record, and because the MeetupEvent queue is gated on
 * `start > now()`, the events behind it do not wait — they expire unpublished. After
 * three rejections of the same payload the record is stepped over with a warning and
 * the batch continues. It stays in the queue and is tried again from zero as soon as
 * its payload changes — and, without waiting for that, once more at the end of any run
 * in which another record WAS accepted ({@see self::reoffer()}), because that
 * acceptance is the only evidence available that the relays are up rather than the
 * payload broken.
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

        /**
         * The records this run stepped over, with the payload it would have sent.
         *
         * @var list<array{0: Meetup|MeetupEvent, 1: Event}> $givenUp
         */
        $givenUp = [];

        /**
         * Whether the relay set accepted anything at all in this run.
         *
         * This is the one piece of evidence {@see NostrEventTransmitter} cannot give per
         * send: its return value is a single boolean, so "the relays are down" and "this
         * event is malformed" arrive as the same answer. An acceptance somewhere else in
         * the same run separates them — see the re-offer below.
         */
        $acceptedSomething = false;

        foreach ($records as $model) {
            if ($startedAt->diffInSeconds(now()) >= self::RUN_BUDGET_SECONDS) {
                $this->warn(sprintf('Run budget of %d s used up; the remaining records wait for the next run.', self::RUN_BUDGET_SECONDS));

                break;
            }

            $event = $model instanceof Meetup
                ? NostrCalendarEventFactory::forMeetup($model)
                : NostrCalendarEventFactory::forMeetupEvent($model, $pubkeyHex);

            /*
             * A payload the relays have already refused three times in a row is stepped
             * over, and the batch goes on — the one thing the failure path below must not
             * do is let a single record hold the queue. See {@see NostrPublishFailures}
             * for why the skip is bound to the payload rather than to the record: edit
             * the text and the next run tries again from zero.
             *
             * No `$result = self::FAILURE` for a skip. The run did the work it could do,
             * and turning a known-bad record into a red exit code every five minutes
             * teaches an operator to ignore the exit code. The warning below is the
             * signal, and it names the record.
             */
            if (NostrPublishFailures::hasGivenUpOn($model, $event)) {
                $this->reportSkipped($model, $modelName);

                $givenUp[] = [$model, $event];

                continue;
            }

            /*
             * The first failure ends the run. The records behind it are left exactly as
             * they were — no coordinate, no fingerprint — so the next run picks them up
             * in the same order; and a relay set that rejects one event is not asked to
             * take the next 24 in the same minute.
             */
            if (! $this->publish($model, $event, $modelName, $hexKey, $pubkeyHex)) {
                $result = self::FAILURE;

                break;
            }

            $acceptedSomething = true;

            if ($model instanceof MeetupEvent && $model->meetup) {
                $meetupsWithNewEvents[$model->meetup->id] = $model->meetup;
            }
        }

        foreach ($this->reoffer($givenUp, $acceptedSomething, $startedAt, $modelName, $hexKey, $pubkeyHex) as $meetup) {
            $meetupsWithNewEvents[$meetup->id] = $meetup;
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
     * Offer the records this run stepped over once more — but only in a run that proved
     * the relay set is up.
     *
     * ## Why this exists
     *
     * Without it the three-strikes rule of {@see NostrPublishFailures} mistakes an
     * outage for a bad payload. With every relay unreachable the run fails at the head
     * of the queue, so that head collects three rejections in fifteen minutes and is
     * given up on; the record behind it becomes the new head and collects its own
     * three. Roughly one healthy record per three runs is marked that way, and since its
     * payload never changed, nothing lifts the mark once the relays come back — a
     * silent, permanent hole in a queue that is now switched on for every meetup.
     *
     * ## Why an acceptance elsewhere in the run is the right trigger
     *
     * {@see NostrEventTransmitter::transmit()} answers with one boolean for the whole
     * relay set, so a single send can never tell "the relays are down" from "this event
     * is malformed". Another record accepted in the same minute can: it is the relays
     * saying yes to something. On that evidence a record that was given up on deserves
     * one more try, and a rejection that follows it means the payload — which is why the
     * count is kept rather than cleared, and why the warning stays.
     *
     * Conversely, when nothing was accepted this run, no re-offer happens at all. That
     * keeps the load rule of the batch above intact: a relay set that is rejecting is
     * not asked to take a second round in the same minute.
     *
     * ## Shape
     *
     * At the END of the batch, never in the middle: the re-offer must not delay the
     * records that have never failed, and it must not consume the run budget they are
     * entitled to — which is why the budget is checked here as well. A rejection does
     * NOT set the run's exit code, because the run did the work it could do; the
     * failure is counted, reported, and the record is offered again in the next run
     * that sees an acceptance.
     *
     * @param  list<array{0: Meetup|MeetupEvent, 1: Event}>  $givenUp  records stepped over, with the payload built for them
     * @return list<Meetup> the meetups whose calendar now has one more event in it
     */
    private function reoffer(array $givenUp, bool $acceptedSomething, CarbonInterface $startedAt, string $modelName, string $hexKey, string $pubkeyHex): array
    {
        if ($givenUp === [] || ! $acceptedSomething) {
            return [];
        }

        /** @var array<int, Meetup> $meetups */
        $meetups = [];

        foreach ($givenUp as [$model, $event]) {
            if ($startedAt->diffInSeconds(now()) >= self::RUN_BUDGET_SECONDS) {
                $this->warn(sprintf('Run budget of %d s used up; the remaining re-offers wait for the next run.', self::RUN_BUDGET_SECONDS));

                break;
            }

            $this->info("Re-offering {$modelName} #{$model->id}: the relays accepted another record in this run.");

            if (! $this->publish($model, $event, $modelName, $hexKey, $pubkeyHex)) {
                $this->reportReofferRejected($model, $modelName);

                continue;
            }

            if ($model instanceof MeetupEvent && $model->meetup) {
                $meetups[$model->meetup->id] = $model->meetup;
            }
        }

        return array_values($meetups);
    }

    /**
     * Report a record that was re-offered on the evidence of an acceptance elsewhere and
     * was rejected anyway.
     *
     * The distinction from {@see self::reportSkipped()} is the whole value of the
     * message: this rejection happened while the relays demonstrably accepted another
     * event, so it is about the payload and not about the network. That is the line an
     * operator should act on.
     */
    private function reportReofferRejected(Meetup|MeetupEvent $model, string $modelName): void
    {
        $attempts = (int) $model->getAttribute(NostrPublishFailures::ATTEMPTS_COLUMN);

        $this->warn(sprintf(
            'Re-offered %s #%d and the relays rejected it again although they accepted another record in this run; its payload needs a change.',
            $modelName,
            $model->id,
        ));

        Log::warning('Nostr calendar publish re-offer rejected', [
            'model' => $modelName,
            'id' => $model->id,
            'attempts' => $attempts,
        ]);
    }

    /**
     * Report a record whose payload the relays have refused {@see NostrPublishFailures::MAX_ATTEMPTS}
     * times in a row and which this run therefore steps over.
     *
     * TO THE LOG as well as to the console, because the run that does this is the
     * scheduled one, whose console output nobody reads. The record is named the way an
     * operator can act on it — model, id, and the count — and the message says the
     * record is still in the queue, so it is not mistaken for a deletion.
     */
    private function reportSkipped(Meetup|MeetupEvent $model, string $modelName): void
    {
        $attempts = (int) $model->getAttribute(NostrPublishFailures::ATTEMPTS_COLUMN);

        $message = sprintf(
            'Skipping %s #%d after %d rejected transmissions of the same payload; the rest of the batch continues and the record is retried when its payload changes.',
            $modelName,
            $model->id,
            $attempts,
        );

        $this->warn($message);

        Log::warning('Nostr calendar publish skipped after repeated failures', [
            'model' => $modelName,
            'id' => $model->id,
            'attempts' => $attempts,
        ]);
    }

    /**
     * Sign and transmit one record; on acceptance store where it went and what it was.
     */
    private function publish(Meetup|MeetupEvent $model, Event $event, string $modelName, string $hexKey, string $pubkeyHex): bool
    {
        $dTag = $model instanceof Meetup
            ? NostrCalendarEventFactory::calendarDTag($model)
            : NostrCalendarEventFactory::eventDTag($model);

        $signer = new Sign;
        $signer->signEvent($event, $hexKey);

        if (! $this->transmit($event)) {
            /*
             * Count the rejection before reporting it: this is what turns the third
             * failure of one payload into the skip above, instead of an endless queue
             * head. Counted per payload, so an outage that hits a different record's
             * payload does not accumulate against this one.
             */
            $attempts = NostrPublishFailures::recordFailure($model, $event);

            $this->error("Failed to publish calendar event for {$modelName} #{$model->id} (attempt {$attempts} with this payload)");

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

        /*
         * The failure tally is about a payload that could not go out; this one did, so
         * there is nothing left to count. Kept honest rather than left standing: a
         * record that failed twice and then succeeded must not carry two attempts into
         * a later run, where one more rejection would silence it three times too early.
         */
        NostrPublishFailures::clear($model);

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
     * THE TWO {@see NostrPublishFailures} COLUMNS ARE IN IT FOR A DIFFERENT REASON, and
     * the difference is worth stating: the query does not gate on them, the run WRITES
     * them, on the path taken when a transmission fails. Missing, they do not degrade
     * quietly — the update throws — but it throws in the middle of a batch, after some
     * records went out and while the operator is already looking at a rejected send. One
     * line naming the column and `php artisan migrate` is a better answer than a stack
     * trace at the worst moment, and it costs a `Schema::hasColumn()` per run.
     *
     * @return list<string> the missing columns as `table.column`, empty when ready
     */
    private function missingGateColumns(string $modelName): array
    {
        $failureColumns = [NostrPublishFailures::ATTEMPTS_COLUMN, NostrPublishFailures::HASH_COLUMN];

        $required = match ($modelName) {
            'Meetup' => ['meetups' => ['nostr_coordinate', 'nostr_publishing_enabled', ...$failureColumns]],
            'MeetupEvent' => [
                'meetup_events' => ['nostr_coordinate', 'cancelled_at', ...$failureColumns],
                'meetups' => ['nostr_publishing_enabled'],
            ],
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
