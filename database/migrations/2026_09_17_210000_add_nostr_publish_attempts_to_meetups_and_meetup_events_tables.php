<?php

use App\Support\NostrPublishFailures;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How often the current payload of a record has been rejected in a row, and which
 * payload that was.
 *
 * WHAT THIS IS FOR. `nostr:publish-calendar` takes its records in queue order and ends
 * the run at the first rejected transmission, leaving everything behind it untouched
 * for the next run. That is right for a relay having a bad minute and wrong for a
 * record the relays will never accept: such a record sits at the head of the queue and
 * every five-minute run spends itself on it again. For `MeetupEvent` the queue is
 * ordered by `start` and gated on `start > now()`, so the records behind it are not
 * merely delayed — the ones whose start passes in the meantime are never published at
 * all. One unpublishable description is enough; the U+2028 defect this migration ships
 * with is one way to produce it, and it will not be the last.
 *
 * With these two columns the run gives up on a payload after
 * {@see NostrPublishFailures::MAX_ATTEMPTS} consecutive rejections and carries on with
 * the rest of the batch.
 *
 * ## Why a hash next to the counter
 *
 * GIVING UP IS ON A PAYLOAD, NOT ON A RECORD. `nostr_publish_failed_hash` holds the
 * {@see \App\Support\NostrPayloadFingerprint} of the payload that was rejected, and the
 * record is only skipped while the payload built today still hashes to that value. Edit
 * the description, and the next run tries again from zero without an operator touching
 * anything. A counter on its own would drop the record from the queue for good — which
 * is the failure mode this repair exists to remove, only quieter.
 *
 * ## Not fillable, no index, no backfill
 *
 * Written by exactly one code path ({@see NostrPublishFailures}) and never by a request,
 * so neither column is added to `Meetup::$fillable` (`MeetupEvent` is unguarded). No
 * index: the skip decision needs a payload built in PHP, so it cannot run in SQL, and
 * the rows are already pre-filtered by `nostr_coordinate IS NULL`. Nothing is
 * backfilled — 0 attempts and an unknown payload is exactly the truth about every
 * existing row.
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
                $blueprint->unsignedSmallInteger(NostrPublishFailures::ATTEMPTS_COLUMN)
                    ->default(0)
                    ->after('nostr_payload_hash');

                // 64 chars: SHA-256 in lowercase hex, the same shape as
                // `nostr_payload_hash` and produced by the same class.
                $blueprint->string(NostrPublishFailures::HASH_COLUMN, 64)
                    ->nullable()
                    ->after(NostrPublishFailures::ATTEMPTS_COLUMN);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn([
                    NostrPublishFailures::ATTEMPTS_COLUMN,
                    NostrPublishFailures::HASH_COLUMN,
                ]);
            });
        }
    }
};
