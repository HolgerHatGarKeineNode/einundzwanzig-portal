<?php

namespace App\Support;

/**
 * What one relay answered to one read, and whether that answer is COMPLETE.
 *
 * `complete` is true only when the relay confirmed the end of the stored events (EOSE)
 * for every subscription of the read. Everything else — a transport error, a CLOSED,
 * a timeout, a relay that simply stopped talking — is UNKNOWN, and an unknown answer
 * carries no events at all: a partial list is indistinguishable from "that is all
 * there is", and treating it as such is the silent zero this class exists to prevent.
 */
final readonly class NostrRelayReadResult
{
    /**
     * @param  list<array<string, mixed>>  $events  Decoded events, only when complete.
     */
    private function __construct(
        public bool $complete,
        public array $events,
        public ?string $failure,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $events
     */
    public static function complete(array $events): self
    {
        return new self(true, $events, null);
    }

    public static function unknown(string $failure): self
    {
        return new self(false, [], $failure);
    }
}
