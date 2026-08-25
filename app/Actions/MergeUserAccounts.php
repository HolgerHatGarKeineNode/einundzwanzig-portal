<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\MergeAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges a second (loser) account into a surviving one, moving every piece of
 * ownership so no Meetup leadership, created record or vote is lost, then
 * deletes the now-empty loser. Runs in a single transaction: because every
 * user FK is cascadeOnDelete, references MUST be repointed before the loser is
 * removed — otherwise deleting the loser would take its Meetups with it.
 *
 * Direction is caller-decided: the survivor is always the account the user
 * keeps logging into; the loser is the freshly proven other identity. The
 * identity proof (Nostr signature / LNURL-auth) happens before this runs — this
 * class trusts that both accounts belong to the same person.
 */
final class MergeUserAccounts
{
    /** Tables carrying a plain `created_by` user FK with no per-user uniqueness. */
    private const CREATED_BY_TABLES = [
        'cities', 'lecturers', 'courses', 'course_events',
        'libraries', 'podcasts', 'episodes', 'meetups', 'library_items',
        'self_hosted_services', 'votes', 'project_proposals', 'meetup_events',
    ];

    /** Tables carrying a plain `user_id` FK with no per-user uniqueness. */
    private const USER_ID_TABLES = [
        'project_proposals', 'orange_pills',
    ];

    /** Ephemeral per-user rows that are simply dropped for the loser. */
    private const DISCARD_TABLES = [
        'sessions', 'login_keys', 'oauth_access_tokens', 'oauth_auth_codes', 'oauth_device_codes',
    ];

    /**
     * Identity/auth fields copied onto the survivor only where it has none yet.
     * public_key etc. carry a unique blind index, so the loser must be gone
     * before these are written — see the ordering in handle().
     *
     * Bis P6 standen hier auch `lightning_address`, `lnurl`, `node_id` und `paynym`.
     * Die vier Spalten laufen aus; sie hier stehen zu lassen haette nach dem Drop einen
     * Schreibversuch auf eine nicht mehr vorhandene Spalte bedeutet. Sie fielen bewusst
     * SCHON in diesem Commit — eine Zuordnung, die ein auslaufendes Feld nicht mehr
     * kopiert, ist auch vor dem Drop unschaedlich, umgekehrt waere es ein Fehler.
     *
     * @var list<string>
     */
    private const IDENTITY_FIELDS = [
        'public_key', 'nostr',
    ];

    /**
     * Encrypted-at-rest fields that must not land in the plaintext audit snapshot.
     *
     * Die vier Lightning-Namen und `lnbits` fielen hier ERST mit der Migration, einen
     * Commit spaeter als in IDENTITY_FIELDS oben — und das war kein Versehen. Der
     * Schwarzungs-Filter laeuft ueber `attributesToArray()` des Verlierers: solange die
     * Spalte existierte, haette ein frueher gestrichener Name die entschluesselte
     * Lightning-Adresse im Klartext ins Audit-JSON geschrieben. Erst als die Spalten fort
     * waren, kostete das Streichen nichts.
     */
    private const REDACTED_SNAPSHOT_FIELDS = [
        'public_key', 'email',
    ];

    /**
     * @param  string  $direction  Free-text label recorded on the audit row (e.g. 'nostr_into_lightning', 'link'); not branched on.
     */
    public function handle(User $survivor, User $loser, string $verifiedIdentity, string $direction, ?int $actorId = null): MergeAudit
    {
        return DB::transaction(function () use ($survivor, $loser, $verifiedIdentity, $direction, $actorId): MergeAudit {
            // Keep the identity fields for the copy step, but never persist the
            // decrypted values into the plaintext audit JSON.
            $identity = [];
            foreach (self::IDENTITY_FIELDS as $field) {
                $identity[$field] = $loser->{$field} ?? null;
            }

            $snapshot = collect($loser->attributesToArray())
                ->map(fn ($value, string $key) => in_array($key, self::REDACTED_SNAPSHOT_FIELDS, true) && $value !== null ? '[redacted]' : $value)
                ->all();

            $counts = [];

            $counts['meetup_user'] = $this->mergeMeetupMemberships($loser->id, $survivor->id);
            $counts['user_badges'] = $this->repointPivot('user_badges', 'badge_id', $loser->id, $survivor->id);
            $counts['library_item_user'] = $this->repointPivot('library_item_user', 'library_item_id', $loser->id, $survivor->id);
            $counts['roles'] = $this->repointMorphPivot('model_has_roles', 'role_id', $loser->id, $survivor->id);
            $counts['permissions'] = $this->repointMorphPivot('model_has_permissions', 'permission_id', $loser->id, $survivor->id);

            foreach (self::CREATED_BY_TABLES as $table) {
                $counts["created_by:$table"] = $this->repointColumn($table, 'created_by', $loser->id, $survivor->id);
            }

            foreach (self::USER_ID_TABLES as $table) {
                $counts["user_id:$table"] = $this->repointColumn($table, 'user_id', $loser->id, $survivor->id);
            }

            $counts['votes'] = $this->dedupeAndRepointVotes($loser->id, $survivor->id);
            $counts['rsvp'] = $this->rewriteRsvpJson($loser->id, $survivor->id);

            foreach (self::DISCARD_TABLES as $table) {
                $this->discardLoserRows($table, $loser->id);
            }

            $this->discardLoserTokens($loser->id);

            $audit = MergeAudit::create([
                'survivor_id' => $survivor->id,
                'loser_id' => $loser->id,
                'direction' => $direction,
                'verified_identity' => $verifiedIdentity,
                'loser_snapshot' => $snapshot,
                'moved_counts' => $counts,
                'actor_id' => $actorId,
            ]);

            // Delete the loser BEFORE stamping identity onto the survivor: both
            // rows briefly holding the same public_key/nostr would collide on
            // the unique blind index. The loser now owns nothing (all FKs moved),
            // so the cascade takes nothing with it.
            $loser->delete();

            foreach (self::IDENTITY_FIELDS as $field) {
                if (($survivor->{$field} ?? null) === null && ($identity[$field] ?? null) !== null) {
                    $survivor->{$field} = $identity[$field];
                }
            }
            $survivor->save();

            return $audit;
        });
    }

    /**
     * Move Meetup memberships, OR-ing the is_leader flag on Meetups where both
     * accounts are already members so a leadership is never demoted by the move.
     */
    private function mergeMeetupMemberships(int $loser, int $survivor): int
    {
        if (! Schema::hasTable('meetup_user')) {
            return 0;
        }

        $survivorMeetups = DB::table('meetup_user')->where('user_id', $survivor)->pluck('meetup_id');

        $sharedLeaderMeetups = DB::table('meetup_user')
            ->where('user_id', $loser)
            ->where('is_leader', true)
            ->whereIn('meetup_id', $survivorMeetups)
            ->pluck('meetup_id');

        if ($sharedLeaderMeetups->isNotEmpty()) {
            DB::table('meetup_user')
                ->where('user_id', $survivor)
                ->whereIn('meetup_id', $sharedLeaderMeetups)
                ->update(['is_leader' => true]);
        }

        return $this->repointPivot('meetup_user', 'meetup_id', $loser, $survivor);
    }

    /**
     * Repoint a composite-key pivot (user_id + $otherKey). Loser rows for a key
     * the survivor already holds are dropped first to avoid a PK collision, then
     * the remaining loser rows are moved. Returns the number of rows moved.
     */
    private function repointPivot(string $table, string $otherKey, int $loser, int $survivor): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $survivorKeys = DB::table($table)->where('user_id', $survivor)->pluck($otherKey);

        DB::table($table)
            ->where('user_id', $loser)
            ->whereIn($otherKey, $survivorKeys)
            ->delete();

        return DB::table($table)
            ->where('user_id', $loser)
            ->update(['user_id' => $survivor]);
    }

    /**
     * spatie's model_has_roles/permissions pivot: keyed by model_id + model_type
     * + role_id/permission_id. Same dedupe-then-move as repointPivot, scoped to
     * the User morph type.
     */
    private function repointMorphPivot(string $table, string $keyColumn, int $loser, int $survivor): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $type = (new User)->getMorphClass();

        $survivorKeys = DB::table($table)
            ->where('model_type', $type)
            ->where('model_id', $survivor)
            ->pluck($keyColumn);

        DB::table($table)
            ->where('model_type', $type)
            ->where('model_id', $loser)
            ->whereIn($keyColumn, $survivorKeys)
            ->delete();

        return DB::table($table)
            ->where('model_type', $type)
            ->where('model_id', $loser)
            ->update(['model_id' => $survivor]);
    }

    private function repointColumn(string $table, string $column, int $loser, int $survivor): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->where($column, $loser)->update([$column => $survivor]);
    }

    /**
     * RSVP status lives in JSON arrays on meetup_events as "id_<userId>|<name>"
     * strings, not FKs. Rewrite the loser's marker to the survivor's and dedupe
     * so a user who RSVP'd on both accounts appears once.
     */
    private function rewriteRsvpJson(int $loser, int $survivor): int
    {
        if (! Schema::hasTable('meetup_events')) {
            return 0;
        }

        $needle = 'id_'.$loser.'|';
        $touched = 0;

        // Fetch all matching rows up front. Iterating with each()/chunk() here
        // would page by LIMIT/OFFSET over a WHERE that shrinks as we rewrite the
        // very column it filters on, silently skipping rows past the first page.
        // ponytail: full fetch is fine — a user RSVP'd to thousands of events is
        // not a real shape; switch to chunkById if that ever changes.
        $rows = DB::table('meetup_events')
            ->where(function ($query) use ($needle): void {
                $query->where('attendees', 'like', '%'.$needle.'%')
                    ->orWhere('might_attendees', 'like', '%'.$needle.'%');
            })
            ->get(['id', 'attendees', 'might_attendees']);

        foreach ($rows as $row) {
            $update = [];
            foreach (['attendees', 'might_attendees'] as $column) {
                $list = json_decode($row->{$column} ?? '[]', true);
                if (! is_array($list)) {
                    continue;
                }
                $update[$column] = json_encode($this->rewriteRsvpList($list, $loser, $survivor));
            }
            if ($update !== []) {
                DB::table('meetup_events')->where('id', $row->id)->update($update);
                $touched++;
            }
        }

        return $touched;
    }

    /**
     * Rewrite the loser's marker to the survivor's, then keep at most one entry
     * per user id. Dedupe MUST key on the "id_<n>" prefix, not the whole
     * "id_<n>|<name>" string: the two accounts carry different display names, so
     * a whole-string unique would leave the survivor listed twice and inflate
     * the attendee count.
     *
     * @param  array<int, mixed>  $list
     * @return list<string>
     */
    private function rewriteRsvpList(array $list, int $loser, int $survivor): array
    {
        $loserPrefix = 'id_'.$loser.'|';
        $seen = [];
        $out = [];

        foreach ($list as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            if (str_starts_with($entry, $loserPrefix)) {
                $entry = 'id_'.$survivor.'|'.substr($entry, strlen($loserPrefix));
            }
            $id = str($entry)->before('|')->value();
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Votes have their own primary key (no unique on user_id + proposal), so a
     * blind repoint would leave two survivor votes on a proposal both accounts
     * voted on, silently inflating the tally. Drop the colliding loser votes
     * first, then move the rest.
     */
    private function dedupeAndRepointVotes(int $loser, int $survivor): int
    {
        if (! Schema::hasTable('votes')) {
            return 0;
        }

        $survivorProposals = DB::table('votes')->where('user_id', $survivor)->pluck('project_proposal_id');

        DB::table('votes')
            ->where('user_id', $loser)
            ->whereIn('project_proposal_id', $survivorProposals)
            ->delete();

        return DB::table('votes')->where('user_id', $loser)->update(['user_id' => $survivor]);
    }

    private function discardLoserRows(string $table, int $loser): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
            DB::table($table)->where('user_id', $loser)->delete();
        }
    }

    /**
     * Sanctum tokens hang off a polymorphic tokenable (no user_id column), so
     * the plain user_id discard misses them. Purge the loser's tokens explicitly.
     */
    private function discardLoserTokens(int $loser): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->where('tokenable_id', $loser)
            ->delete();
    }
}
