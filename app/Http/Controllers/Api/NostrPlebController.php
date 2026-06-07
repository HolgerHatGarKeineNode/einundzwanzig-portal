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
     * Nostr-Pubkeys (npubs) der Community
     *
     * Liefert die eindeutigen npub-Public-Keys aller Nutzer mit hinterlegtem Nostr-Profil.
     *
     * @return Collection<int, string>
     */
    public function __invoke(): Collection
    {
        return User::query()
            ->select([
                'email',
                'public_key',
                'lightning_address',
                'lnurl',
                'node_id',
                'paynym',
                'lnbits',
                'nostr',
                'id',
            ])
            ->whereNotNull('nostr')
            ->where('nostr', 'like', 'npub1%')
            ->orderByDesc('id')
            ->get()
            ->unique('nostr')
            ->pluck('nostr');
    }
}
