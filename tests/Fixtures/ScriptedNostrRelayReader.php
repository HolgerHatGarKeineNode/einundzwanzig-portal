<?php

namespace Tests\Fixtures;

use App\Support\NostrRelayReader;
use App\Support\NostrRelayReadResult;

/**
 * A {@see NostrRelayReader} that answers from a script instead of a socket, and records
 * every filter it was asked for.
 *
 * Per relay URL either a list of events (the relay answers completely and returns every
 * scripted event that matches a filter of the read), or null (the relay never confirms
 * EOSE — the "unknown" case).
 */
class ScriptedNostrRelayReader extends NostrRelayReader
{
    /**
     * @var array<string, list<array<string, mixed>>|null>
     */
    public array $relays = [];

    /**
     * Every read, in order: relay URL and the filters it carried.
     *
     * @var list<array{relay: string, filters: list<array<string, mixed>>}>
     */
    public array $reads = [];

    /**
     * Events a relay pushes although no filter of the read asks for them — a relay is not
     * obliged to be honest, and the ingest's validation is what has to hold then. Each is
     * delivered ONCE per relay, on its next read, the way a push arrives once.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    public array $unrequested = [];

    /**
     * Seconds the clock is moved on by every read — the cheap way to let a test reach the
     * command's wall-clock budget without waiting for it.
     */
    public int $secondsPerRead = 0;

    /**
     * @param  list<array<string, mixed>>  $filters
     */
    public function read(string $relayUrl, array $filters): NostrRelayReadResult
    {
        $this->reads[] = ['relay' => $relayUrl, 'filters' => $filters];

        if ($this->secondsPerRead > 0) {
            \Illuminate\Support\Carbon::setTestNow(now()->addSeconds($this->secondsPerRead));
        }

        $events = $this->relays[$relayUrl] ?? null;

        if ($events === null) {
            return NostrRelayReadResult::unknown('no EOSE for subscription');
        }

        $pushed = $this->unrequested[$relayUrl] ?? [];
        unset($this->unrequested[$relayUrl]);

        return NostrRelayReadResult::complete(array_values([
            ...array_filter(
                $events,
                fn (array $event): bool => array_any($filters, fn (array $filter): bool => self::matches($event, $filter)),
            ),
            ...$pushed,
        ]));
    }

    /**
     * NIP-01 filter matching for the keys the ingest uses.
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $filter
     */
    public static function matches(array $event, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            $matches = match (true) {
                $key === 'kinds' => in_array($event['kind'], $value, true),
                $key === 'authors' => in_array($event['pubkey'], $value, true),
                $key === 'since' => $event['created_at'] >= $value,
                $key === 'limit' => true,
                str_starts_with($key, '#') => array_any(
                    $event['tags'],
                    fn (array $tag): bool => $tag[0] === substr($key, 1) && in_array($tag[1] ?? null, $value, true),
                ),
                default => false,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }
}
