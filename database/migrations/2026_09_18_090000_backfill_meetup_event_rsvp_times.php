<?php

use App\Support\MeetupEventAttendance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every portal RSVP that already exists a time, so that no old Nostr answer can
 * overrule it (hybrid RSVP, D12a).
 *
 * ## The direction, and why it is this one
 *
 * `meetup_event_rsvp_times` was introduced with an empty table, and the merge read a
 * MISSING row as "answered before the table existed", i.e. as OLDER than any Nostr
 * answer. That is wrong in the one case it decides: a member who pressed "Ich komme" on
 * the portal last year, and a kind 31925 of his key created at any moment before that —
 * the Nostr answer would silently win and could take him off the list. A stranger cannot
 * cause it (the key must be linked to the account), but a user with two devices can do
 * it to himself, and he would never know why.
 *
 * After this backfill:
 *
 * - every entry on `attendees` / `might_attendees` that belongs to an account has a row
 *   carrying THIS migration's timestamp — so only a Nostr answer created (or first seen)
 *   AFTER the deployment can override it, which is exactly "the newer answer wins";
 * - a MISSING row now means "this account never answered in the portal", and a Nostr
 *   answer of that account stands unopposed — no comparison to make;
 * - the residual case (a list entry whose row was later removed, e.g. by an account
 *   merge, see the Restposten) is decided FOR the portal in
 *   {@see MeetupEventAttendance}: an answer of unknown time is not beaten by a Nostr
 *   answer of unknown relative age.
 *
 * ## What it writes
 *
 * One row per (event, account) found in either list, with `answered_at = now()`.
 * `insertOrIgnore` keeps a row that already exists (someone answered between the two
 * deployments) and its real, later time. Guest entries (`anon_<session>|…`) carry no
 * account and are skipped; ids of deleted accounts are skipped too, or the foreign key
 * would refuse the insert.
 *
 * `down()` is deliberately a NO-OP, and that is not laziness: the rows this migration
 * writes are indistinguishable from the answers users gave afterwards, so any undo would
 * either delete real answers or leave the backfilled ones behind. The table itself is
 * dropped by the migration that creates it; a rollback past this one therefore loses
 * nothing that this one added.
 */
return new class extends Migration
{
    private const CHUNK = 500;

    public function up(): void
    {
        if (! Schema::hasTable('meetup_event_rsvp_times')) {
            return;
        }

        $answeredAt = now();

        DB::table('meetup_events')
            ->select(['id', 'attendees', 'might_attendees'])
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($events) use ($answeredAt): void {
                $rows = [];

                foreach ($events as $event) {
                    foreach ($this->userIdsOf($event->attendees, $event->might_attendees) as $userId) {
                        $rows[] = [
                            'meetup_event_id' => $event->id,
                            'user_id' => $userId,
                            'answered_at' => $answeredAt,
                        ];
                    }
                }

                if ($rows === []) {
                    return;
                }

                $existingUsers = DB::table('users')
                    ->whereIn('id', array_column($rows, 'user_id'))
                    ->pluck('id')
                    ->all();

                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row): bool => in_array($row['user_id'], $existingUsers, true),
                ));

                if ($rows !== []) {
                    DB::table('meetup_event_rsvp_times')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        // See the class docblock: a backfilled row and a real answer are the same row.
    }

    /**
     * The account ids in the two stored lists, without duplicates and without guests.
     *
     * @return list<int>
     */
    private function userIdsOf(mixed ...$lists): array
    {
        $ids = [];

        foreach ($lists as $list) {
            foreach ((array) json_decode((string) $list, true) as $entry) {
                if (preg_match('/^id_(\d+)\|/', (string) $entry, $matches) === 1) {
                    $ids[(int) $matches[1]] = true;
                }
            }
        }

        return array_keys($ids);
    }
};
