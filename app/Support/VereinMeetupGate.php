<?php

namespace App\Support;

use App\Http\Controllers\Api\MeetupMapController;
use App\Http\Controllers\Api\VereinGatedMeetupController;
use App\Models\Meetup;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use swentel\nostr\Key\Key as NostrKey;

/**
 * Association-member gate for meetups — the single source of truth for which
 * meetups have a Nostr room.
 *
 * A meetup is "gated" when it is visible on the map (visible_on_map) AND at
 * least one genuine EINUNDZWANZIG association member is attached to it through
 * the meetup_user pivot. Association members come from the current year of the
 * verein.einundzwanzig.space API; the match runs over the Nostr pubkey
 * (users.nostr, plaintext npub, canonically normalized to hex; users.public_key
 * is CipherSweet-encrypted and is deliberately NOT used).
 *
 * Shared by {@see VereinGatedMeetupController} (full
 * list, server-to-server) and {@see MeetupMapController}
 * (only the has_room flag, via the cached id set).
 */
class VereinMeetupGate
{
    /**
     * Gated meetups as an enriched list (id, slug, name, country_code,
     * logo_url, member_npubs). For the server-to-server endpoint.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function gatedMeetups(bool $leadersOnly = false): Collection
    {
        $vereinUserIds = $this->vereinUserIds();

        if ($vereinUserIds->isEmpty()) {
            return collect();
        }

        $constrain = $this->memberConstraint($vereinUserIds, $leadersOnly);

        return Meetup::query()
            ->where('visible_on_map', true)
            ->whereHas('users', $constrain)
            ->with([
                'city:id,name,country_id',
                'city.country:id,code',
                'media',
                // Only the matched association members per meetup — constant query count.
                'users' => $constrain,
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Meetup $meetup): array => [
                'id' => $meetup->id,
                'slug' => $meetup->slug,
                'name' => $meetup->name,
                'country_code' => str($meetup->city?->country?->code)->upper()->value(),
                // getFirstMedia guard: empty instead of the fallback placeholder URL when there is no logo.
                'logo_url' => $meetup->getFirstMedia('logo') ? $meetup->getFirstMediaUrl('logo', 'thumb') : '',
                'member_npubs' => $meetup->users
                    ->pluck('nostr')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * id set of the gated meetups (every pivot member counts, NOT only leaders).
     * For the has_room flag on the map. Cached (the verein API + pivot query must
     * not run on every /api/meetups request); fail-soft through vereinUserIds.
     *
     * @return Collection<int, int>
     */
    public function gatedMeetupIds(): Collection
    {
        return Cache::remember('verein.gated_ids.'.date('Y'), now()->addMinutes(5), function (): Collection {
            $vereinUserIds = $this->vereinUserIds();

            if ($vereinUserIds->isEmpty()) {
                return collect();
            }

            return Meetup::query()
                ->where('visible_on_map', true)
                ->whereHas('users', $this->memberConstraint($vereinUserIds, false))
                ->pluck('id');
        });
    }

    /**
     * Constraint for whereHas (Builder) AND the users eager load (Relation): both
     * proxy whereIn/where onto the same query builder; the meetup_user pivot is
     * joined in either case (cf. {@see Meetup::scopeLedBy}).
     */
    private function memberConstraint(Collection $vereinUserIds, bool $leadersOnly): Closure
    {
        return function ($query) use ($vereinUserIds, $leadersOnly) {
            $query->whereIn('users.id', $vereinUserIds->all());

            if ($leadersOnly) {
                $query->where('meetup_user.is_leader', true);
            }

            return $query;
        };
    }

    /**
     * Portal user ids whose nostr npub (→ hex) is part of the association member set.
     *
     * @return Collection<int, int>
     */
    private function vereinUserIds(): Collection
    {
        $vereinHexes = $this->vereinMemberHexes();

        if ($vereinHexes->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereNotNull('nostr')
            ->where('nostr', '!=', '')
            ->get(['id', 'nostr'])
            ->filter(fn (User $user): bool => $vereinHexes->contains($this->npubToHex($user->nostr)))
            ->pluck('id');
    }

    /**
     * hex pubkeys of the current year's association members. Fail-soft: an empty
     * collection on error (callers then return an empty list / has_room=false, no
     * 500). Successful responses are cached briefly; the year is always dynamic.
     *
     * @return Collection<int, string>
     */
    private function vereinMemberHexes(): Collection
    {
        $year = date('Y');

        $members = Cache::get("verein.members.{$year}");

        if ($members === null) {
            try {
                $response = Http::withHeaders(['User-Agent' => 'curl/8.5.0'])
                    ->timeout(8)
                    ->get("https://verein.einundzwanzig.space/api/members/{$year}");

                if ($response->successful()) {
                    $members = $response->json();
                    Cache::put("verein.members.{$year}", $members, now()->addMinutes(5));
                } else {
                    $members = [];
                }
            } catch (\Throwable) {
                $members = [];
            }
        }

        return collect(is_array($members) ? $members : [])
            ->map(function (array $member): ?string {
                // Prefers the hex pubkey; falls back to the npub.
                if (filled($member['pubkey'] ?? null)) {
                    return mb_strtolower((string) $member['pubkey']);
                }

                return $this->npubToHex($member['npub'] ?? null);
            })
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * npub (bech32) → hex, fault-tolerant (null for an invalid/empty value).
     *
     * Public so other gates built on the same association-membership matching
     * (e.g. App\Support\BoardGate) normalize npubs the same way instead of
     * reimplementing the conversion.
     */
    public function npubToHex(?string $npub): ?string
    {
        if (! is_string($npub) || ! str_starts_with($npub, 'npub1')) {
            return null;
        }

        try {
            return mb_strtolower((new NostrKey)->convertToHex($npub));
        } catch (\Throwable) {
            return null;
        }
    }
}
