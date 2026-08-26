<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Meetup;
use Illuminate\Support\Facades\DB;

/**
 * Merges a duplicate meetup (loser) into the one that stays (survivor), then
 * deletes the loser. Runs in a single transaction.
 *
 * The order is not cosmetic. `meetup_events.meetup_id` and `meetup_user.meetup_id`
 * are both cascadeOnDelete, so deleting the loser first would silently take its
 * events and its leaders with it — the very things the merge exists to keep.
 * Everything is repointed BEFORE the delete.
 *
 * The caller decides the direction. This class does not judge which of the two
 * is the real meetup; it only moves what the loser carries and hands back a
 * snapshot so the move can be reconstructed afterwards.
 */
final class MergeMeetups
{
    /**
     * Master data copied onto the survivor, but only where the survivor has
     * nothing yet. Never overwrites — a filled field on the survivor is the
     * decision that was already made, and the loser is not evidence against it.
     *
     * @var list<string>
     */
    private const FILL_IF_EMPTY = [
        'intro', 'telegram_link', 'webpage', 'twitter_username',
        'matrix_group', 'nostr', 'simplex', 'signal', 'community',
    ];

    /**
     * @return array{survivor: int, loser: int, snapshot: array<string, mixed>, moved: array<string, int>, filled: list<string>}
     */
    public function handle(Meetup $survivor, Meetup $loser): array
    {
        if ($survivor->getKey() === $loser->getKey()) {
            throw new \InvalidArgumentException('Ein Meetup kann nicht in sich selbst zusammengefuehrt werden.');
        }

        return DB::transaction(function () use ($survivor, $loser): array {
            $snapshot = $loser->attributesToArray();

            $moved = [
                'meetup_events' => $this->repointEvents($loser->getKey(), $survivor->getKey()),
                'meetup_user' => $this->repointMembers($loser->getKey(), $survivor->getKey()),
            ];

            $filled = $this->fillEmptyFields($survivor, $loser);

            $loser->delete();

            return [
                'survivor' => $survivor->getKey(),
                'loser' => $loser->getKey(),
                'snapshot' => $snapshot,
                'moved' => $moved,
                'filled' => $filled,
            ];
        });
    }

    private function repointEvents(int $loser, int $survivor): int
    {
        return DB::table('meetup_events')
            ->where('meetup_id', $loser)
            ->update(['meetup_id' => $survivor]);
    }

    /**
     * Move memberships, OR-ing is_leader so a leadership is never demoted by the
     * move, and dropping loser rows for users the survivor already carries.
     *
     * The dedupe is not optional: meetup_user has no primary key and no unique on
     * (meetup_id, user_id) — see its migration. A blind repoint would leave the
     * same user twice on the survivor, and Meetup::leaders() would then list them
     * twice in the public npub payload.
     */
    private function repointMembers(int $loser, int $survivor): int
    {
        $survivorUsers = DB::table('meetup_user')
            ->where('meetup_id', $survivor)
            ->pluck('user_id');

        $sharedLeaders = DB::table('meetup_user')
            ->where('meetup_id', $loser)
            ->where('is_leader', true)
            ->whereIn('user_id', $survivorUsers)
            ->pluck('user_id');

        if ($sharedLeaders->isNotEmpty()) {
            DB::table('meetup_user')
                ->where('meetup_id', $survivor)
                ->whereIn('user_id', $sharedLeaders)
                ->update(['is_leader' => true]);
        }

        DB::table('meetup_user')
            ->where('meetup_id', $loser)
            ->whereIn('user_id', $survivorUsers)
            ->delete();

        return DB::table('meetup_user')
            ->where('meetup_id', $loser)
            ->update(['meetup_id' => $survivor]);
    }

    /**
     * @return list<string> the fields that were actually taken over
     */
    private function fillEmptyFields(Meetup $survivor, Meetup $loser): array
    {
        $filled = [];

        foreach (self::FILL_IF_EMPTY as $field) {
            $mine = $survivor->{$field};
            $theirs = $loser->{$field};

            if (($mine === null || trim((string) $mine) === '') && $theirs !== null && trim((string) $theirs) !== '') {
                $survivor->{$field} = $theirs;
                $filled[] = $field;
            }
        }

        if ($filled !== []) {
            $survivor->save();
        }

        return $filled;
    }
}
