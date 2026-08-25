<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Collection;

#[Group(name: 'Community', weight: 7)]
class NostrPlebController extends Controller
{
    /**
     * Nostr pubkeys (npubs) of the community
     *
     * Returns the unique npub public keys of all users with a stored Nostr profile.
     *
     * @return Collection<int, string>
     */
    public function __invoke(): Collection
    {
        /*
         * Nur die zwei Spalten, die diese Antwort braucht.
         *
         * Hier standen bis P6 alle fuenf Lightning-Spalten plus `email` und
         * `public_key` — ausgeliefert wurde davon nie eine. Das war nicht nur
         * ueberfluessig: `email`, `public_key`, `lightning_address`, `lnurl`, `node_id`,
         * `paynym` und `lnbits` sind CipherSweet-verschluesselt, also kostete jede Zeile
         * sieben Entschluesselungen fuer ein Feld, das im Klartext danebensteht.
         * `nostr` ist keines davon.
         */
        return User::query()
            ->select(['id', 'nostr'])
            ->whereNotNull('nostr')
            ->where('nostr', 'like', 'npub1%')
            ->orderByDesc('id')
            ->get()
            ->unique('nostr')
            ->pluck('nostr');
    }
}
