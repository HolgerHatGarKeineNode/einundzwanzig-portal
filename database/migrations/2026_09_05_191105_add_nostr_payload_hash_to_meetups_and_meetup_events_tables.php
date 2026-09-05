<?php

use App\Support\NostrPayloadFingerprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #92: the fingerprint of the payload each record was last published with.
 *
 * `nostr_coordinate` answers WHERE a record sits on the relays; it cannot answer
 * WHETHER what sits there is still what the portal would publish today. That second
 * question is the whole of #92: `nostr:publish-calendar` is gated on
 * `nostr_coordinate IS NULL`, so a record it has published keeps the payload it went
 * out with for good, and every later change to the published payload — the geography
 * `t` tags of #69, the `start_tzid` repair of #104 — reaches only the records
 * published after it. This column is the answer to the second question, and therefore
 * the trigger the issue asked for.
 *
 * ## What goes in it
 *
 * The SHA-256 of the canonical form of the last event this portal successfully
 * transmitted for the record — kind, tags and content, and deliberately nothing else.
 * See {@see NostrPayloadFingerprint} for why `created_at`, `id`, `sig` and
 * `pubkey` are excluded; excluding `created_at` in particular is what keeps a record
 * from looking changed on every single run.
 *
 * A hash and not the payload itself: nothing ever needs to read the old payload back,
 * only to compare it, and storing a signed event per record would put a second copy of
 * the published catalogue in the database with no reader.
 *
 * ## NULL, and why nothing is backfilled
 *
 * NULL means UNKNOWN, in exactly two situations: the record was never published, or it
 * was published before this column existed. The two are told apart by
 * `nostr_coordinate`, not by this column.
 *
 * A record with a coordinate and a NULL hash is therefore treated as STALE by
 * `nostr:republish-calendar --changed`, and re-sent once. That is a deliberate
 * decision, not an omission: the alternative — computing today's payload for every
 * already-published record and writing it in as if it had been sent — would assert
 * something the portal does not know, and it would assert it in the one direction that
 * silently reproduces the defect this issue reports, because it would mark exactly the
 * back catalogue of #69 and #104 as up to date. The cost of the honest direction is one
 * idempotent re-send per already-published record, paced by the scheduler and
 * self-terminating: the hash is written on success, so each record is re-sent once and
 * never again.
 *
 * ## Not fillable, not indexed
 *
 * Not added to `Meetup::$fillable` (`MeetupEvent` is unguarded) on purpose — this column
 * is written by exactly one code path, {@see NostrPayloadFingerprint::remember()},
 * and never by a request. No index: the staleness comparison cannot run in SQL (the
 * current payload has to be built in PHP first), so an index would be paid for and never
 * used. The rows are pre-filtered by `nostr_coordinate IS NOT NULL`, which is the
 * selective predicate.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = ['meetups', 'meetup_events'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // 64 chars: SHA-256 in lowercase hex. Fixed width rather than `text`,
                // because unlike a coordinate this value has one shape forever.
                $blueprint->string('nostr_payload_hash', 64)
                    ->nullable()
                    ->after('nostr_coordinate');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('nostr_payload_hash');
            });
        }
    }
};
