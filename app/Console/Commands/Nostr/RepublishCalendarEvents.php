<?php

namespace App\Console\Commands\Nostr;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
use App\Support\NostrPayloadFingerprint;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;

/**
 * Re-sends meetups and meetup events that were ALREADY published to Nostr, so a fix to
 * the published payload reaches the back catalogue (issues #92 and #104).
 *
 * ## Why a separate command
 *
 * `nostr:publish-calendar` is gated on `nostr_coordinate IS NULL` and nothing ever sets
 * that column back, so it is a one-way door: a record it has published keeps the payload
 * it was published with, for good. Everything already on a relay therefore carries
 * `start_tzid: Europe/Berlin` (the #104 defect) and sits in a calendar with no `a` tags.
 * Kinds 31923 and 31924 are parameterized-replaceable, so the repair is a re-send under
 * the same `d` tag with a newer `created_at`; what was missing is the trigger.
 *
 * ## Answers to the three questions issue #92 left open
 *
 * WHICH RECORDS — either, and the flag says which. Without `--changed` this is the
 * operator's blunt instrument: every record that carries a coordinate, whether or not
 * its payload moved, narrowed with `--meetup` and `--limit`. Past events are included —
 * their kind 31923 is still on the relays and still reachable from the calendar, so it
 * still carries whatever it was published with. With `--changed` only the records whose
 * payload the current code no longer builds the same way are re-sent, compared through
 * {@see NostrPayloadFingerprint} against `nostr_payload_hash`.
 *
 * An earlier version of this docblock argued that deciding "changed" was not worth "a
 * schema change and a second source of truth, to save a re-send that is idempotent
 * anyway". That reasoning holds for a command a human runs and stops being true the
 * moment the trigger is automatic, which is what the issue actually asked for: without
 * the comparison the automatic form is a blanket re-send of ~400 signed events to every
 * relay on a timer, and an error in the payload would be re-broadcast on that timer for
 * ever. The fingerprint is what makes the automatic path self-terminating — it is
 * written on success, so every record is re-sent once per real change and then stops.
 *
 * WHO DECIDES — both, in their own mode, and they do not collide. `--changed` is
 * scheduled (see `routes/console.php`) and is the automatic half. The default mode is
 * still an operator decision and is still not scheduled: a re-send of payloads nobody
 * complained about is a burst against every relay in the list and belongs to a human.
 * A forced run in EITHER mode records the fingerprint of what it sent, so a manual
 * repair is not immediately re-done by the scheduled one.
 *
 * WHAT THROTTLES IT — a pause between records, `--sleep`, default 2 seconds. The
 * numbers it is set against, read from the public API on 2026-09-04: 307 meetups and
 * 76 upcoming events portal-wide, so the whole catalogue is under 400 records. At two
 * seconds that is about 13 minutes and a sustained 0.5 events per second per relay,
 * against a burst of 400 EVENT frames in the time it takes to open the sockets. Two
 * seconds is not a measurement of any particular relay's limit — that is a property of
 * their configuration, not of ours — it is the smallest pause that keeps a full-catalogue
 * repair below one event per second while still finishing inside a coffee break, and it
 * is an option precisely because an operator who knows their relays can raise or lower
 * it. For comparison, the scheduled publisher sends at most one event every five
 * minutes; `--limit` is the second lever, and a repair can simply be run in batches.
 *
 * ## Safety
 *
 * DRY RUN IS THE DEFAULT. Without `--force` nothing leaves the process; the command
 * prints what it would send. The confirmation flag guards the DESTRUCTIVE direction on
 * purpose: forgetting a flag then costs a printed plan, whereas with `--dry-run` as an
 * opt-in the same slip would cost hundreds of writes to public relays that cannot be
 * taken back. Add `-v` to dump the full unsigned event JSON per record.
 *
 * IDEMPOTENT, in both senses. On the wire, running it twice re-sends the same payload
 * under the same `d` tag and the relay replaces the event in place; the only difference
 * between two runs is `created_at`, `id` and `sig`. Locally, a successful send writes
 * exactly one column, `nostr_payload_hash`, and writes the same value for the same
 * payload — so `--changed` sends on the first run and nothing at all on the second.
 * `nostr_coordinate` is never written here; the address does not move.
 *
 * KEY MISMATCH IS A SKIP, NOT A SILENT REWRITE. The coordinate is recomputed from the
 * configured key and compared with the stored one. If they differ, the configured key
 * is not the key the record was published under, and re-sending would create a SECOND
 * event under a new address while the old one stays on the relays unchanged. A key
 * rotation is an operational decision; a repair command reports it and moves on.
 */
class RepublishCalendarEvents extends Command
{
    protected $signature = 'nostr:republish-calendar
        {--model= : Meetup or MeetupEvent — omit to do both}
        {--meetup= : Restrict to one meetup, by id or slug}
        {--changed : Only records whose payload the current code no longer builds the same way}
        {--limit=0 : Stop after this many records; 0 means no limit}
        {--sleep=2 : Seconds to wait between records; see the class docblock}
        {--force : Actually transmit. Without it this command is a dry run}';

    protected $description = 'Re-publish already-published meetups/events so payload fixes reach the back catalogue';

    public function __construct(private readonly NostrEventTransmitter $transmitter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $privateKey = config('services.nostr.publisher_key');

        if (! $privateKey) {
            $this->error('NOSTR_PUBLISHER_NSEC is not set.');

            return self::FAILURE;
        }

        $modelName = $this->option('model');

        if ($modelName !== null && ! in_array($modelName, ['Meetup', 'MeetupEvent'], true)) {
            $this->error("Unsupported model: {$modelName}");

            return self::FAILURE;
        }

        /*
         * The same schema gate as PublishCalendarEvents, for the same reason (#72):
         * SQLite turns a double-quoted identifier that matches no column into a STRING
         * LITERAL, so on an unmigrated database `where "nostr_coordinate" is not null`
         * compares the constant 'nostr_coordinate' against NULL. It is never true, this
         * command would report "nothing to republish" and exit 0, and an operator
         * reading exit codes could not tell that apart from a repaired catalogue.
         */
        foreach (['meetups', 'meetup_events'] as $table) {
            if (! Schema::hasColumn($table, 'nostr_coordinate')) {
                $this->error("Missing column: {$table}.nostr_coordinate — run php artisan migrate. Without it this command finds nothing to republish and would exit 0 as if the catalogue were repaired.");

                return self::FAILURE;
            }

            /*
             * The same gate for the fingerprint column, but against the OPPOSITE
             * failure. A missing `nostr_coordinate` makes this command do nothing; a
             * missing `nostr_payload_hash` makes `--changed` do everything, because
             * every record then reads back a null fingerprint, counts as stale and is
             * re-sent — on every scheduled run, for ever. That is the burst this whole
             * mechanism exists to prevent, so an unmigrated database must stop the
             * command rather than be absorbed by it.
             */
            if ($this->option('changed') && ! Schema::hasColumn($table, NostrPayloadFingerprint::COLUMN)) {
                $this->error(sprintf(
                    'Missing column: %s.%s — run php artisan migrate. Without it --changed considers every published record stale and would re-send the whole catalogue on every run.',
                    $table,
                    NostrPayloadFingerprint::COLUMN,
                ));

                return self::FAILURE;
            }
        }

        $meetupFilter = $this->option('meetup');
        $meetup = null;

        if ($meetupFilter !== null) {
            $meetup = Meetup::query()
                ->where('slug', $meetupFilter)
                ->when(ctype_digit((string) $meetupFilter), fn ($query) => $query->orWhere('id', (int) $meetupFilter))
                ->first();

            if (! $meetup) {
                $this->error("No meetup matches: {$meetupFilter}");

                return self::FAILURE;
            }
        }

        $key = new Key;
        $hexKey = str_starts_with($privateKey, 'nsec') ? $key->convertToHex($privateKey) : $privateKey;
        $pubkeyHex = $key->getPublicKey($hexKey);

        $records = $this->records($modelName, $meetup);
        $skipped = 0;

        /*
         * THE FILTER RUNS BEFORE `--limit`, not after, and that ordering is the
         * difference between a mechanism that drains and one that stalls. A limit
         * applied first would hand the batch ten records that may all be up to date,
         * send nothing, and pick the same ten on the next run — for ever. Filtering
         * first makes the limit a cap on WORK DONE rather than on rows looked at, so
         * every scheduled run makes progress while there is progress to make.
         *
         * Key mismatches are dropped here too, for the same reason: a record published
         * under a rotated key can never be re-sent, and leaving it in the candidate set
         * would let a handful of them occupy the batch on every run and starve the
         * records that can actually be repaired.
         */
        if ($this->option('changed')) {
            $candidates = $records->count();
            [$records, $skipped] = $this->onlyStale($records, $pubkeyHex);

            $this->line(sprintf(
                'Checked %d published record(s): %d carry a payload this code no longer builds.',
                $candidates,
                $records->count(),
            ));
        }

        $limit = max(0, (int) $this->option('limit'));

        if ($limit > 0) {
            $records = $records->take($limit);
        }

        if ($records->isEmpty()) {
            $this->info('Nothing to republish.');

            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} record(s) published under another key.");
            }

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $sleepSeconds = max(0.0, (float) $this->option('sleep'));

        $this->line(sprintf(
            '%s %d record(s)%s.',
            $force ? 'Republishing' : 'DRY RUN — would republish',
            $records->count(),
            $force ? sprintf(', %s s apart', rtrim(rtrim(number_format($sleepSeconds, 2, '.', ''), '0'), '.')) : '',
        ));

        $sent = 0;
        $failed = 0;
        $first = true;

        foreach ($records as $record) {
            $event = $this->eventFor($record, $pubkeyHex);
            $coordinate = $this->coordinateFor($record, $event, $pubkeyHex);

            // In `--changed` mode these were already dropped during the scan, so this
            // check never fires twice for the same record. It stays because the default
            // mode does not scan at all.
            if (! $this->publishedUnderConfiguredKey($record, $coordinate)) {
                $skipped++;

                continue;
            }

            $this->line($this->describe($record, $event));

            if ($this->output->isVerbose()) {
                $this->line('  '.$event->toJson());
            }

            if (! $force) {
                continue;
            }

            // The pause goes BEFORE every record but the first, so a single-record run
            // and the last record of a batch do not pay for a wait nobody waits on.
            if (! $first && $sleepSeconds > 0) {
                usleep((int) round($sleepSeconds * 1_000_000));
            }

            $first = false;

            $signer = new Sign;
            $signer->signEvent($event, $hexKey);

            if ($this->transmitter->transmit($event, config('services.nostr.relays', []))) {
                /*
                 * The fingerprint of the event that was ACTUALLY signed and sent, not
                 * of the one built during the `--changed` scan a moment earlier. The
                 * two are the same payload in every realistic case, but the invariant
                 * that matters is "this column names what the relays hold", and only
                 * the event that left the process can make that true.
                 *
                 * Recorded in both modes on purpose. A forced default-mode repair that
                 * left the column alone would leave every record it just fixed looking
                 * stale, and the scheduled `--changed` run would re-send the entire
                 * catalogue again within the hour — the manual and the automatic path
                 * would keep undoing each other's reason to exist.
                 */
                NostrPayloadFingerprint::remember($record, $event);
                $sent++;

                continue;
            }

            $this->error(sprintf('Failed to republish %s #%d (%s)', class_basename($record), $record->id, $coordinate));
            $failed++;
        }

        if (! $force) {
            $this->newLine();
            $this->warn('Nothing was sent. Re-run with --force to transmit to '.count((array) config('services.nostr.relays', [])).' relay(s).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Republished {$sent}, failed {$failed}, skipped {$skipped}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The records to re-send, events before calendars.
     *
     * The order is cosmetic rather than semantic — a calendar's `a` tags are read from
     * the database, which this command does not write, so no record's payload depends
     * on another having gone out first. Events first simply means a reader following a
     * refreshed calendar finds refreshed events behind it.
     *
     * `nostr_publishing_enabled` IS RESPECTED, even though every record here was
     * published while it was on. A re-send is a new signed event with a new id and a
     * new `created_at`, so it is a publishing act, not an edit of a local row, and an
     * organiser who switched the flag off has withdrawn consent to those. The cost of
     * that choice is stated rather than hidden: such records keep the payload they were
     * published with, wrong `start_tzid` included, and the portal has no way to withdraw
     * them either — it never emits a NIP-09 deletion. That is a real gap, and a
     * different one from this issue.
     *
     * @return Collection<int, Meetup|MeetupEvent>
     */
    private function records(?string $modelName, ?Meetup $meetup): Collection
    {
        $events = $modelName === 'Meetup'
            ? collect()
            : MeetupEvent::query()
                ->with('meetup.city.country')
                ->whereNotNull('nostr_coordinate')
                ->whereHas('meetup', fn ($query) => $query->where('nostr_publishing_enabled', true))
                ->when($meetup, fn ($query) => $query->where('meetup_id', $meetup->id))
                ->orderBy('start')
                ->orderBy('id')
                ->get();

        $meetups = $modelName === 'MeetupEvent'
            ? collect()
            : Meetup::query()
                ->with('city.country')
                ->whereNotNull('nostr_coordinate')
                ->where('nostr_publishing_enabled', true)
                ->when($meetup, fn ($query) => $query->whereKey($meetup->id))
                ->orderBy('id')
                ->get();

        return $events->values()->concat($meetups->values());
    }

    /**
     * The records whose payload the relays no longer hold, and how many were dropped
     * because they were published under a different key.
     *
     * ONE EVENT IS BUILT PER CANDIDATE HERE, and the winners are built a second time in
     * the send loop. That is deliberate rather than sloppy: `created_at` is stamped at
     * build time, so an event carried over from this scan would go out with a timestamp
     * up to `--sleep × batch size` seconds older than its own transmission. Harmless for
     * NIP-01 — it is still far newer than the event it replaces — but the second build
     * is a hash and a handful of already-loaded relations, and it keeps the timestamp
     * honest. The relations are eager-loaded by {@see self::records()}, so the scan does
     * not go back to the database per record; the kind 31924 arm is the exception, since
     * a calendar's `a` tags are a query by construction.
     *
     * @param  Collection<int, Meetup|MeetupEvent>  $records
     * @return array{Collection<int, Meetup|MeetupEvent>, int}
     */
    private function onlyStale(Collection $records, string $pubkeyHex): array
    {
        $skipped = 0;

        $stale = $records->filter(function (Model $record) use ($pubkeyHex, &$skipped): bool {
            $event = $this->eventFor($record, $pubkeyHex);

            if (! $this->publishedUnderConfiguredKey($record, $this->coordinateFor($record, $event, $pubkeyHex))) {
                $skipped++;

                return false;
            }

            return NostrPayloadFingerprint::isStale($record, $event);
        })->values();

        return [$stale, $skipped];
    }

    /**
     * Whether the configured key is the key this record was published under, warning
     * once if it is not.
     *
     * Re-sending under a different key would create a SECOND event at a new address
     * while the old one stays on the relays unchanged, so the answer is a skip and a
     * report. A key rotation is an operational decision, not something a repair command
     * gets to make on the operator's behalf.
     */
    private function publishedUnderConfiguredKey(Model $record, string $coordinate): bool
    {
        if ($coordinate === $record->nostr_coordinate) {
            return true;
        }

        $this->warn(sprintf(
            'Skipped %s #%d: stored as %s but the configured key would publish it as %s.',
            class_basename($record),
            $record->id,
            (string) $record->nostr_coordinate,
            $coordinate,
        ));

        return false;
    }

    private function coordinateFor(Model $record, Event $event, string $pubkeyHex): string
    {
        return NostrCalendarEventFactory::coordinate($event->getKind(), $pubkeyHex, $this->dTagFor($record));
    }

    private function eventFor(Model $record, string $pubkeyHex): Event
    {
        return match (true) {
            $record instanceof Meetup => NostrCalendarEventFactory::forMeetup($record),
            $record instanceof MeetupEvent => NostrCalendarEventFactory::forMeetupEvent($record, $pubkeyHex),
        };
    }

    private function dTagFor(Model $record): string
    {
        return match (true) {
            $record instanceof Meetup => NostrCalendarEventFactory::calendarDTag($record),
            $record instanceof MeetupEvent => NostrCalendarEventFactory::eventDTag($record),
        };
    }

    /**
     * One line per record, carrying the fields this repair is about: the `d` tag that
     * makes it a replacement rather than a duplicate, the `created_at` that decides
     * whether a relay accepts it, and — per kind — the two things issue #104 fixed.
     */
    private function describe(Model $record, Event $event): string
    {
        $dTag = $event->getTag('d')[0][1] ?? '';
        $detail = $event->getKind() === 31924
            ? sprintf('%d event(s) listed', count($event->getTag('a')))
            : 'start_tzid='.($event->getTag('start_tzid')[0][1] ?? '(omitted)');

        return sprintf(
            '  kind %d  d=%-24s created_at=%d  %s',
            $event->getKind(),
            $dTag,
            $event->getCreatedAt(),
            $detail,
        );
    }
}
