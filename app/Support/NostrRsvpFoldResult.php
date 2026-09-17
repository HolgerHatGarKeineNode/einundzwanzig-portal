<?php

namespace App\Support;

/**
 * What {@see NostrRsvpFold} decided: rows to write, rows to remove, and how many events
 * were thrown away for which reason.
 */
final readonly class NostrRsvpFoldResult
{
    /**
     * @param  list<array{meetup_event_id: int, pubkey: string, nostr_event_id: string, d_tag: string, status: string, rsvp_created_at: int}>  $upserts
     * @param  list<array{meetup_event_id: int, pubkey: string}>  $deletions
     * @param  array<string, int>  $dropped  reason => count, see NostrRsvpFold::DROP_* constants
     * @param  int  $verifications  signature checks actually spent
     * @param  bool  $verificationBudgetExhausted  true when events were left unverified — the run is INCOMPLETE and its cursors must not move
     */
    public function __construct(
        public array $upserts,
        public array $deletions,
        public array $dropped,
        public int $verifications = 0,
        public bool $verificationBudgetExhausted = false,
    ) {}
}
