<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use App\Rules\ValidNpub;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Support\Collection;

/**
 * Public leader npubs per meetup, for badge verification in external clients.
 *
 * The consumer scans a badge, verifies the Schnorr signature locally (BIP-340)
 * and then has to answer the second question: is the signer a known organiser
 * of THIS meetup? Only the portal knows that — leadership lives in
 * meetup_user.is_leader and is granted by another leader (see MeetupLeaderController).
 *
 * Deliberately grouped BY meetup and not returned as one flat allowlist: a npub
 * proves nothing beyond the meetup it is listed under. A consumer that flattens
 * this into a global "is an organiser" set turns meetup creation — open to every
 * signed-in user, see MeetupPolicy::create() — into self-service verification.
 *
 * Only npubs, no name and no avatar: the minimum the verification needs.
 *
 * Undocumented by request ({@see ExcludeRouteFromDocs}) — reachable, but not
 * advertised in the API reference while the shape settles.
 */
class PublicMeetupLeaderController extends Controller
{
    /**
     * @return Collection<int, array{meetup_id: int, npubs: list<string>}>
     */
    #[ExcludeRouteFromDocs]
    public function __invoke(): Collection
    {
        return Meetup::query()
            ->where('visible_on_map', true)
            ->select(['id'])
            // Two queries in total, regardless of the meetup count: the pivot
            // filter lives in the eager load, not in a per-meetup lookup.
            ->with(['users' => fn ($query) => $query
                ->wherePivot('is_leader', true)
                ->whereNotNull('users.nostr')
                ->select(['users.id', 'users.nostr']),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Meetup $meetup): array => [
                'meetup_id' => $meetup->getKey(),
                'npubs' => $meetup->users
                    ->pluck('nostr')
                    ->map(fn (string $npub): string => trim($npub))
                    ->filter(fn (string $npub): bool => self::isWellFormedNpub($npub))
                    ->unique()
                    ->values()
                    ->all(),
            ])
            // Meetups without a single leader npub carry no information for the
            // consumer and only inflate a payload the app caches offline.
            ->filter(fn (array $row): bool => $row['npubs'] !== [])
            ->values();
    }

    /**
     * Shape check on the stored npub — bech32 prefix, length and alphabet.
     *
     * Deliberately a format check and not {@see ValidNpub}: that rule
     * bech32-decodes, which is right on the write path (once per appointment) but
     * would run per npub on every app start here. What this has to catch is not a
     * forged key — the write path already validates — but a malformed leftover
     * reaching the consumer, who cannot decode it and has no way to tell why.
     */
    private static function isWellFormedNpub(string $npub): bool
    {
        // bech32 excludes 1, b, i and o so that the characters cannot be confused.
        return preg_match('/^npub1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{58}$/', $npub) === 1;
    }
}
