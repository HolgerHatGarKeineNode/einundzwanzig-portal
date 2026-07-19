<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use swentel\nostr\Key\Key as NostrKey;

/**
 * Vereinsmitglied-gegatete Meetup-Liste (Server-zu-Server, Bearer-Auth).
 *
 * Liefert nur Meetups, die über die meetup_user-Pivot mindestens ein echtes
 * EINUNDZWANZIG-Vereinsmitglied führen. Grundlage dafür, im Nostr-Client nur
 * Meetups mit echtem Vereinsbezug als Chat-Räume anzulegen.
 *
 * Gate-Quelle: die Vereinsmitglieder-Liste des laufenden Jahres von
 * verein.einundzwanzig.space. Der Abgleich läuft über den Nostr-Pubkey: die
 * Portal-Spalte users.nostr (Klartext-npub) wird kanonisch nach hex normalisiert
 * und gegen die (npub ODER hex) der Verein-Antwort geprüft — robust in beide
 * Richtungen. users.public_key (CipherSweet-verschlüsselt, anderer Key-Typ) wird
 * bewusst NICHT verwendet.
 */
#[Group(name: 'Meetups', weight: 3)]
class VereinGatedMeetupController extends Controller
{
    /**
     * Vereinsmitglied-gegatete Meetups.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(Request $request): Collection
    {
        $leadersOnly = $request->boolean('leaders_only');

        // hex-Pubkeys aller aktuellen Vereinsmitglieder (kanonische Vergleichsbasis).
        $vereinHexes = $this->vereinMemberHexes();

        if ($vereinHexes->isEmpty()) {
            return collect();
        }

        // Portal-User, deren nostr-npub (→ hex) in der Verein-Menge liegt.
        $vereinUserIds = User::query()
            ->whereNotNull('nostr')
            ->where('nostr', '!=', '')
            ->get(['id', 'nostr'])
            ->filter(fn (User $user): bool => $vereinHexes->contains($this->npubToHex($user->nostr)))
            ->pluck('id');

        if ($vereinUserIds->isEmpty()) {
            return collect();
        }

        // Gilt für whereHas (Builder) UND den users-Eager-Load (Relation): beide
        // proxen whereIn/where auf denselben Query-Builder; die meetup_user-Pivot
        // ist in beiden Fällen gejoint (vgl. Meetup::scopeLedBy).
        $constrainToVereinMembers = function ($query) use ($vereinUserIds, $leadersOnly) {
            $query->whereIn('users.id', $vereinUserIds->all());

            if ($leadersOnly) {
                $query->where('meetup_user.is_leader', true);
            }

            return $query;
        };

        return Meetup::query()
            // Chat-Raum-Basis = nur sichtbare Meetups (wie /api/mobile/meetups).
            ->where('visible_on_map', true)
            ->whereHas('users', $constrainToVereinMembers)
            ->with([
                'city:id,name,country_id',
                'city.country:id,code',
                'media',
                // Nur die gematchten Vereinsmitglieder je Meetup — konstante Query-Zahl.
                'users' => $constrainToVereinMembers,
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
     * hex-Pubkeys der Vereinsmitglieder des laufenden Jahres. Fail-soft: bei Fehler
     * eine leere Collection (der Endpunkt liefert dann eine leere Liste, kein 500).
     * Erfolgreiche Antworten werden kurz gecacht; das Jahr ist immer dynamisch.
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
