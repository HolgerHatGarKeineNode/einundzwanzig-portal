<?php

namespace App\Console\Commands\Nostr;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
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
 * WHICH RECORDS — every record that carries a coordinate, not only the ones whose
 * payload demonstrably changed. Deciding "changed" would mean storing the published
 * event and diffing it, i.e. a schema change and a second source of truth, to save a
 * re-send that is idempotent anyway. `--meetup` and `--limit` are how an operator
 * narrows it instead. Past events are included: their kind 31923 is still on the relays
 * and still reachable from the calendar, so it still carries the wrong zone.
 *
 * WHO DECIDES — an operator, deliberately. This command is NOT scheduled, and
 * `routes/console.php` is deliberately left alone. A bulk re-send is a burst against
 * every relay in the list, and it re-sends payloads nobody complained about. The
 * automatic half of the problem is solved where it belongs and incrementally: publishing
 * an event refreshes its calendar (see {@see PublishCalendarEvents}).
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
 * IDEMPOTENT. Running it twice re-sends the same payload under the same `d` tag; the
 * relay replaces the event in place, and no column here is written, so there is no
 * local state to corrupt. The only visible difference between two runs is
 * `created_at`, `id` and `sig`.
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
        $limit = max(0, (int) $this->option('limit'));

        if ($limit > 0) {
            $records = $records->take($limit);
        }

        if ($records->isEmpty()) {
            $this->info('Nothing to republish.');

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
        $skipped = 0;
        $first = true;

        foreach ($records as $record) {
            $event = $this->eventFor($record, $pubkeyHex);
            $coordinate = NostrCalendarEventFactory::coordinate(
                $event->getKind(),
                $pubkeyHex,
                $this->dTagFor($record),
            );

            if ($coordinate !== $record->nostr_coordinate) {
                $this->warn(sprintf(
                    'Skipped %s #%d: stored as %s but the configured key would publish it as %s.',
                    class_basename($record),
                    $record->id,
                    (string) $record->nostr_coordinate,
                    $coordinate,
                ));
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
