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

        return self::SUCCESS;
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
