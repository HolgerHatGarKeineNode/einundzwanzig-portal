<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #97: the calendar feed needs a revision counter it can raise TWICE on
 * the same second.
 *
 * SEQUENCE in the feed is `updated_at` plus a hard-wired `+1` while the event is
 * cancelled (issue #56). That expression can only ever say two things, so a
 * reinstatement lands BELOW the cancellation a subscriber already holds — and
 * RFC 5546 §2.1.5 makes the higher number the one that wins, which means the
 * reversal would be discarded and the calendar would keep showing an event the
 * portal shows as live. The offset stored here is the counter #56's own docblock
 * asked for when it wrote down this cost.
 *
 * WHY AN OFFSET AND NOT A PLAIN SEQUENCE COLUMN. Every subscriber out there
 * holds a number near 1.7 billion, because `updated_at` is the base. A counter
 * that started at 0 would emit a SEQUENCE far below what those clients already
 * have, and by the same §2.1.5 rule every future revision of every event would
 * be ignored for good. Keeping `updated_at` as the base and adding a stored
 * offset to it means the number only ever grows.
 *
 * THE BACKFILL IS THE SAME ARGUMENT ONE STEP FURTHER. A row that is cancelled
 * right now was published as `updated_at + 1`; leaving its offset at the default
 * 0 would make the next thing this portal emits for it one LOWER than the copy
 * in every subscriber's client. Cancelled rows therefore start at 1 — the value
 * that reproduces today's output exactly — and everything else at 0.
 *
 * Appended at the END of the row rather than with ->after(), for the reason the
 * `cancelled_at` migration next door gives: on MySQL a column added last is the
 * case that has been ALGORITHM=INSTANT the longest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->unsignedInteger('calendar_sequence_offset')->default(0);
        });

        DB::table('meetup_events')
            ->whereNotNull('cancelled_at')
            ->update(['calendar_sequence_offset' => 1]);
    }

    public function down(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->dropColumn('calendar_sequence_offset');
        });
    }
};
