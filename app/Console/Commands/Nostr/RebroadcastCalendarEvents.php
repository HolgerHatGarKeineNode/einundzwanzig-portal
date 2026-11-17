<?php

namespace App\Console\Commands\Nostr;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarAddress;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrCalendarHorizon;
use App\Support\NostrEventTransmitter;
use App\Support\NostrEventVerifier;
use App\Support\NostrPayloadFingerprint;
use App\Support\NostrRelayReader;
use App\Support\NostrRelays;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;

/**
 * Closes the gap between "marked published" and "held by the relays": reads every
 * configured relay first and sends each relay only what it lacks.
 *
 * ## Why
 *
 * Measured on 2026-11-17 by anonymous reads: 684 upcoming dates carried a
 * `nostr_coordinate`; nos.lol held 77 of them, relay.damus.io 94, relay.primal.net one,
 * the association's own relay none. `nostr:publish-calendar` never looks at a record
 * again once it has a coordinate, and `nostr:republish-calendar --changed` only when its
 * payload changed — so nothing ever refilled a relay.
 *
 * ## What is sent — and what never is
 *
 * ONLY WHAT LIES AHEAD, AND NO FURTHER THAN THE HORIZON (the operator's rules of
 * 2026-11-17: no re-broadcast of the past, and no date more than a year ahead —
 * {@see NostrCalendarHorizon}):
 *
 *  - kind 31923 for dates that start within the horizon and are not cancelled,
 *  - kind 31924 for meetups that have at least one such date.
 *
 * A past, cancelled or far-future date is neither read nor sent here, whatever a relay
 * holds. Nearest start first, so an interrupted run has delivered what matters. The
 * rule is checked twice: in the query ({@see self::candidates()}) and again at the one
 * place an event leaves ({@see self::mayBeSent()}).
 *
 * ## Read first
 *
 * Per relay one REQ by `#d`. A relay that does not answer completely (no EOSE, CLOSED,
 * socket error) is left alone in this run: without a read nothing is "missing" there.
 * A copy counts only if its id and signature verify and it is signed by the portal key.
 *
 * ## Which event is sent — `created_at`
 *
 * Kind 31923/31924 are addressable: per `d` a relay keeps the newest `created_at`, and
 * a client that merges several relays shows the newest. So a repair must never
 * introduce an OLDER or a needlessly NEWER version:
 *
 *  - Some relay holds a copy whose payload is what the portal builds today: THAT signed
 *    event (the NIP-01 winner across the relays) is forwarded verbatim — same id, same
 *    signature, same `created_at` — to the relays that lack the current payload.
 *    Nothing is re-signed, nothing flaps, and all relays converge on one event id.
 *  - No relay holds the current payload: ONE new event is signed, with a `created_at`
 *    later than every copy seen, and offered to the relays that lack it.
 *
 * ## Pace
 *
 * `--per-relay` bounds the events offered to one relay per run, `--sleep` spaces them.
 * The defaults stay below what the relays were seen to keep from one burst (ten on
 * nos.lol, eight on relay.damus.io). What is not sent now is found missing again by a
 * later run; the cursor walks the records in scope from the nearest start outwards and
 * starts over.
 */
class RebroadcastCalendarEvents extends Command
{
    protected $signature = 'nostr:rebroadcast-calendar
        {--model= : Meetup or MeetupEvent — omit to do both}
        {--limit=40 : Records checked per model and run; 0 means all}
        {--per-relay=8 : Events offered to one relay per run at most; 0 means no cap}
        {--sleep=3 : Seconds between two sends}
        {--max-seconds=480 : Stop starting new work after this many seconds; 0 means no limit}
        {--force : Actually transmit. Without it: read the relays and print what is missing}';

    protected $description = 'Send upcoming calendar events to the relays that do not hold them (reads first; dry run without --force)';

    /**
     * `#d` values per filter. Addressable events answer with at most one event per
     * value, so 40 stays below the smallest `max_limit` among the configured relays
     * (relay.nostr.net: 100).
     */
    private const D_TAGS_PER_FILTER = 40;

    private const CURSOR_CACHE_PREFIX = 'nostr:rebroadcast-calendar:cursor:';

    /**
     * @var array<string, array{held: int, missing: int, sent: int, accepted: int, refused: int, deferred: int, note: ?string}>
     */
    private array $perRelay = [];

    private bool $hasSent = false;

    /**
     * @var array<string, array<int, string>> model name => record id => the start it is ordered by
     */
    private array $sortKeys = [];

    public function __construct(private readonly NostrEventTransmitter $transmitter)
    {
        parent::__construct();
    }

    public function handle(NostrRelayReader $reader): int
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

        $relays = NostrRelays::all();

        if ($relays === []) {
            $this->error('No relays configured (NOSTR_RELAYS).');

            return self::FAILURE;
        }

        $key = new Key;
        $hexKey = str_starts_with($privateKey, 'nsec') ? $key->convertToHex($privateKey) : $privateKey;
        $pubkeyHex = $key->getPublicKey($hexKey);

        $force = (bool) $this->option('force');
        $startedAt = now();
        $maxSeconds = max(0, (int) $this->option('max-seconds'));
        $this->hasSent = false;
        $this->perRelay = array_fill_keys($relays, ['held' => 0, 'missing' => 0, 'sent' => 0, 'accepted' => 0, 'refused' => 0, 'deferred' => 0, 'note' => null]);

        $checked = 0;

        // Dates before calendars: a date published in this run belongs to nothing new,
        // but the order keeps the scarce per-relay budget for the dates themselves.
        foreach (array_filter(['MeetupEvent', 'Meetup'], fn (string $name): bool => $modelName === null || $modelName === $name) as $name) {
            $records = $this->candidates($name, $pubkeyHex, $force);

            foreach ($records->chunk(self::D_TAGS_PER_FILTER) as $batch) {
                if ($maxSeconds > 0 && $startedAt->diffInSeconds(now()) >= $maxSeconds) {
                    $this->warn("Time budget of {$maxSeconds} s used up; the rest waits for the next run.");

                    break 2;
                }

                $this->processBatch($batch->values(), $reader, $relays, $hexKey, $pubkeyHex, $force);
                $checked += $batch->count();

                if ($force) {
                    $last = (int) $batch->last()->id;
                    Cache::forever(self::CURSOR_CACHE_PREFIX.$name, $this->sortKeys[$name][$last].'|'.$last);
                }
            }
        }

        return $this->report($checked, $force);
    }

    /**
     * The records this run looks at: published under the configured key, not cancelled,
     * starting within the horizon ({@see NostrCalendarHorizon}) — NEAREST START FIRST,
     * so a run that is interrupted has delivered what matters. A calendar takes the
     * start of its meetup's nearest date.
     *
     * A scheduled run takes `--limit` records, continues after the last run's cursor
     * and starts over at the end, so every record is looked at in turn. A dry run always
     * starts at the nearest date and moves no cursor.
     *
     * @return Collection<int, Meetup|MeetupEvent>
     */
    private function candidates(string $modelName, string $pubkeyHex, bool $force): Collection
    {
        $limit = max(0, (int) $this->option('limit'));
        $order = $modelName === 'Meetup' ? $this->calendarOrder($pubkeyHex) : $this->dateOrder($pubkeyHex);

        $this->sortKeys[$modelName] = array_column($order, 0, 1);

        if ($limit > 0) {
            $cursor = $force ? Cache::get(self::CURSOR_CACHE_PREFIX.$modelName) : null;
            $offset = 0;

            if (is_string($cursor) && str_contains($cursor, '|')) {
                [$cursorKey, $cursorId] = explode('|', $cursor, 2);

                foreach ($order as $position => [$key, $id]) {
                    if (strcmp($key, $cursorKey) < 0 || ($key === $cursorKey && $id <= (int) $cursorId)) {
                        $offset = $position + 1;
                    }
                }
            }

            // After the cursor first, then from the nearest date again; never twice.
            $order = array_slice([...array_slice($order, $offset), ...array_slice($order, 0, $offset)], 0, $limit);
        }

        $ids = array_column($order, 1);

        $records = $modelName === 'Meetup'
            ? Meetup::query()->with('city.country')->whereKey($ids)->get()
            : MeetupEvent::query()->with('meetup.city.country')->whereKey($ids)->get();

        $position = array_flip($ids);

        return $records->sortBy(fn (Meetup|MeetupEvent $record): int => $position[$record->id])->values();
    }

    /**
     * @return list<array{0: string, 1: int}> [start, id] of every date in scope, nearest first
     */
    private function dateOrder(string $pubkeyHex): array
    {
        return NostrCalendarHorizon::constrain(
            MeetupEvent::query()
                ->where('nostr_coordinate', 'like', NostrCalendarAddress::KIND_TIME_BASED_EVENT.":{$pubkeyHex}:%")
                ->whereNull('cancelled_at')
                ->whereHas('meetup', fn ($meetup) => $meetup->where('nostr_publishing_enabled', true))
                ->toBase()
        )
            ->orderBy('start')
            ->orderBy('id')
            ->get(['id', 'start'])
            ->map(fn (object $row): array => [(string) $row->start, (int) $row->id])
            ->all();
    }

    /**
     * @return list<array{0: string, 1: int}> [start of the nearest date in scope, meetup id], nearest first
     */
    private function calendarOrder(string $pubkeyHex): array
    {
        $nearest = NostrCalendarHorizon::constrain(
            MeetupEvent::query()->whereNotNull('nostr_coordinate')->whereNull('cancelled_at')->toBase()
        )
            ->groupBy('meetup_id')
            ->selectRaw('meetup_id, min(start) as nearest_start')
            ->pluck('nearest_start', 'meetup_id');

        return Meetup::query()
            ->where('nostr_publishing_enabled', true)
            ->where('nostr_coordinate', 'like', NostrCalendarAddress::KIND_CALENDAR.":{$pubkeyHex}:%")
            ->whereKey($nearest->keys())
            ->pluck('id')
            ->map(fn (int $id): array => [(string) $nearest[$id], $id])
            ->sort(fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: ($a[1] - $b[1]))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Meetup|MeetupEvent>  $batch
     * @param  list<string>  $relays
     */
    private function processBatch(Collection $batch, NostrRelayReader $reader, array $relays, string $hexKey, string $pubkeyHex, bool $force): void
    {
        /** @var array<string, array{record: Meetup|MeetupEvent, event: Event, fingerprint: string}> $wanted keyed by d tag */
        $wanted = [];

        foreach ($batch as $record) {
            $event = $record instanceof Meetup
                ? NostrCalendarEventFactory::forMeetup($record)
                : NostrCalendarEventFactory::forMeetupEvent($record, $pubkeyHex);

            $dTag = $record instanceof Meetup
                ? NostrCalendarEventFactory::calendarDTag($record)
                : NostrCalendarEventFactory::eventDTag($record);

            $wanted[$dTag] = ['record' => $record, 'event' => $event, 'fingerprint' => NostrPayloadFingerprint::of($event)];
        }

        $kind = $batch->first() instanceof Meetup
            ? NostrCalendarAddress::KIND_CALENDAR
            : NostrCalendarAddress::KIND_TIME_BASED_EVENT;

        /** @var array<string, array<string, array<string, mixed>>> $held relay => d tag => verified copy */
        $held = [];

        foreach ($relays as $relay) {
            $copies = $this->readRelay($reader, $relay, $kind, $pubkeyHex, array_keys($wanted));

            if ($copies !== null) {
                $held[$relay] = $copies;
            }
        }

        foreach ($wanted as $dTag => $want) {
            $winner = null;
            $missingOn = [];

            foreach ($held as $relay => $copies) {
                $copy = $copies[$dTag] ?? null;

                if ($copy !== null && ($winner === null || self::replaces($copy, $winner))) {
                    $winner = $copy;
                }

                if ($copy !== null && self::fingerprintOf($copy) === $want['fingerprint']) {
                    $this->perRelay[$relay]['held']++;
                } else {
                    $this->perRelay[$relay]['missing']++;
                    $missingOn[] = $relay;
                }
            }

            if ($missingOn === [] || ! $force) {
                continue;
            }

            $this->send($want['record'], $want['event'], $winner, $want['fingerprint'], $missingOn, $hexKey);
        }
    }

    /**
     * One REQ to one relay for the batch. Null when the relay did not answer completely
     * — then nothing is known about it and nothing is sent to it.
     *
     * @param  list<string>  $dTags
     * @return array<string, array<string, mixed>>|null d tag => the relay's winning verified copy
     */
    private function readRelay(NostrRelayReader $reader, string $relay, int $kind, string $pubkeyHex, array $dTags): ?array
    {
        // A relay that could not be read once is not asked again in this run.
        if (str_starts_with((string) $this->perRelay[$relay]['note'], 'not read')) {
            return null;
        }

        $result = $reader->read($relay, [[
            'kinds' => [$kind],
            'authors' => [$pubkeyHex],
            '#d' => $dTags,
            'limit' => count($dTags),
        ]]);

        if (! $result->complete) {
            $this->perRelay[$relay]['note'] = 'not read: '.($result->failure ?? 'incomplete answer');

            return null;
        }

        $copies = [];

        foreach ($result->events as $event) {
            // A relay is not obliged to be honest: only a copy that verifies, is signed
            // by the portal key and answers the question asked is evidence.
            if (! NostrEventVerifier::verify($event) || $event['pubkey'] !== $pubkeyHex || $event['kind'] !== $kind) {
                continue;
            }

            $dTag = self::dTagOf($event);

            if ($dTag === null || ! in_array($dTag, $dTags, true)) {
                continue;
            }

            if (! isset($copies[$dTag]) || self::replaces($event, $copies[$dTag])) {
                $copies[$dTag] = $event;
            }
        }

        return $copies;
    }

    /**
     * @param  array<string, mixed>|null  $winner  the newest verified copy any relay holds
     * @param  list<string>  $missingOn
     */
    private function send(Meetup|MeetupEvent $record, Event $event, ?array $winner, string $fingerprint, array $missingOn, string $hexKey): void
    {
        if (! $this->mayBeSent($record)) {
            return;
        }

        $perRelay = max(0, (int) $this->option('per-relay'));
        $targets = [];

        foreach ($missingOn as $relay) {
            if ($perRelay > 0 && $this->perRelay[$relay]['sent'] >= $perRelay) {
                $this->perRelay[$relay]['deferred']++;

                continue;
            }

            $targets[] = $relay;
        }

        if ($targets === []) {
            return;
        }

        $forwarded = $winner !== null && self::fingerprintOf($winner) === $fingerprint;

        if ($forwarded) {
            $event = (new Event)->populate((object) $winner);
        } else {
            $event->setCreatedAt(max(now()->getTimestamp(), (int) ($winner['created_at'] ?? 0) + 1));
            (new Sign)->signEvent($event, $hexKey);
        }

        $sleepSeconds = max(0.0, (float) $this->option('sleep'));

        if ($this->hasSent && $sleepSeconds > 0) {
            usleep((int) round($sleepSeconds * 1_000_000));
        }

        $this->hasSent = true;

        $answers = $this->transmitter->transmit($event, $targets);

        foreach ($answers->answers as $answer) {
            if (! $answer->offered) {
                $this->perRelay[$answer->relay]['deferred']++;
                $this->perRelay[$answer->relay]['note'] ??= $answer->message;

                continue;
            }

            $this->perRelay[$answer->relay]['sent']++;
            $this->perRelay[$answer->relay][$answer->accepted ? 'accepted' : 'refused']++;

            if (! $answer->accepted) {
                $this->perRelay[$answer->relay]['note'] ??= 'refused: '.($answer->message ?? 'no answer');
            }
        }

        // A NEW event that at least one relay took is what the relays now carry for this
        // record — the same bookkeeping as after a publish.
        if (! $forwarded && $answers->acceptedCount() > 0) {
            NostrPayloadFingerprint::remember($record, $event);
        }

        $this->line(sprintf(
            '  %s kind %d d=%s (%s): %s',
            $forwarded ? 'forwarded' : 'signed',
            $event->getKind(),
            self::dTagOf(['tags' => $event->getTags()]) ?? '?',
            substr($event->getId(), 0, 12),
            $answers->summary(),
        ));
    }

    /**
     * The last gate before an event leaves: nothing about the past, nothing cancelled,
     * nothing further ahead than the horizon.
     * Deliberately independent of {@see self::candidates()} — a change to that query
     * must not be able to turn this command into a re-broadcast of the back catalogue.
     */
    private function mayBeSent(Meetup|MeetupEvent $record): bool
    {
        if ($record instanceof MeetupEvent) {
            // Asked of the database, not of the record loaded when the run began: a run
            // reads relays for minutes, and a date an organiser cancelled or moved out
            // of the window meanwhile must not leave as if nothing had happened.
            return NostrCalendarHorizon::constrain(
                MeetupEvent::query()->whereKey($record->id)->whereNull('cancelled_at')
            )->exists();
        }

        return NostrCalendarHorizon::constrain(
            $record->meetupEvents()->whereNotNull('nostr_coordinate')->whereNull('cancelled_at')
        )->exists();
    }

    private function report(int $checked, bool $force): int
    {
        $this->newLine();
        $this->line(sprintf('%s %d upcoming record(s) on %d relay(s).', $force ? 'Checked' : 'DRY RUN — checked', $checked, count($this->perRelay)));

        $readable = 0;
        $missing = 0;

        foreach ($this->perRelay as $relay => $stats) {
            $unread = $stats['note'] !== null && str_starts_with($stats['note'], 'not read');
            $readable += $unread ? 0 : 1;
            $missing += $stats['missing'];

            $this->line(sprintf(
                '  %-36s held %d, missing %d%s%s',
                $relay,
                $stats['held'],
                $stats['missing'],
                $force ? sprintf(', sent %d (accepted %d, refused %d), deferred %d', $stats['sent'], $stats['accepted'], $stats['refused'], $stats['deferred']) : '',
                $stats['note'] !== null ? ' — '.$stats['note'] : '',
            ));

            if ($force && ($stats['refused'] > 0 || $unread)) {
                Log::warning('Nostr calendar rebroadcast: relay refused or could not be read', ['relay' => $relay] + $stats);
            }
        }

        if (! $force) {
            $this->warn("Nothing was sent. {$missing} event copies are missing; re-run with --force to send them.");
        }

        // Exit 1 only when the run could not do its job at all: no relay could be read.
        return $readable === 0 && $checked > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * NIP-01: the newer `created_at` replaces the older; on a tie the lower id stays.
     *
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $current
     */
    private static function replaces(array $candidate, array $current): bool
    {
        if ($candidate['created_at'] !== $current['created_at']) {
            return $candidate['created_at'] > $current['created_at'];
        }

        return strcmp($candidate['id'], $current['id']) < 0;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function fingerprintOf(array $event): string
    {
        return NostrPayloadFingerprint::of((new Event)->populate((object) $event));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function dTagOf(array $event): ?string
    {
        foreach ($event['tags'] as $tag) {
            if (($tag[0] ?? null) === 'd' && is_string($tag[1] ?? null)) {
                return $tag[1];
            }
        }

        return null;
    }
}
