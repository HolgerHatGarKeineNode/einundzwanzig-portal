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
 * Vereinsmitglied-Gate für Meetups — die eine Quelle der Wahrheit dafür, welche
 * Meetups einen Nostr-Raum haben.
 *
 * Ein Meetup ist „gegatet", wenn es auf der Karte sichtbar ist (visible_on_map)
 * UND über die meetup_user-Pivot mindestens ein echtes EINUNDZWANZIG-Vereins-
 * mitglied führt. Vereinsmitglieder kommen aus der verein.einundzwanzig.space-API
 * des laufenden Jahres; der Abgleich läuft über den Nostr-Pubkey (users.nostr,
 * Klartext-npub, kanonisch nach hex normalisiert; users.public_key ist Cipher-
 * Sweet-verschlüsselt und wird bewusst NICHT verwendet).
 *
 * Geteilt von {@see VereinGatedMeetupController} (volle
 * Liste, Server-zu-Server) und {@see MeetupMapController}
 * (nur das has_room-Flag über die gecachte id-Menge).
 */
class VereinMeetupGate
{
    /**
     * Gegatete Meetups als angereicherte Liste (id, slug, name, country_code,
     * logo_url, member_npubs). Für den Server-zu-Server-Endpunkt.
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
                // Nur die gematchten Vereinsmitglieder je Meetup — konstante Query-Zahl.
                'users' => $constrain,
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Meetup $meetup): array => [
                'id' => $meetup->id,
                'slug' => $meetup->slug,
                'name' => $meetup->name,
                'country_code' => str($meetup->city?->country?->code)->upper()->value(),
                // getFirstMedia-Guard: leer statt Fallback-Platzhalter-URL, wenn kein Logo.
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
     * id-Menge der gegateten Meetups (jedes Pivot-Mitglied zählt, NICHT nur Leader).
     * Für das has_room-Flag auf der Karte. Gecacht (verein-API + Pivot-Query dürfen
     * nicht bei jedem /api/meetups-Request neu laufen); fail-soft über vereinUserIds.
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
     * Constraint für whereHas (Builder) UND den users-Eager-Load (Relation): beide
     * proxen whereIn/where auf denselben Query-Builder; die meetup_user-Pivot ist in
     * beiden Fällen gejoint (vgl. {@see Meetup::scopeLedBy}).
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
     * Portal-User-ids, deren nostr-npub (→ hex) in der Vereinsmitglieder-Menge liegt.
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
     * hex-Pubkeys der Vereinsmitglieder des laufenden Jahres. Fail-soft: bei Fehler
     * eine leere Collection (Aufrufer liefern dann leere Liste / has_room=false, kein
     * 500). Erfolgreiche Antworten werden kurz gecacht; das Jahr ist immer dynamisch.
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
                // Bevorzugt den hex-pubkey; fällt auf den npub zurück.
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
     * npub (bech32) → hex, fehlertolerant (null bei ungültigem/leerem Wert).
     */
    private function npubToHex(?string $npub): ?string
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
