<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\VereinMeetupGate;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Association-member-gated meetup list (server-to-server, bearer auth).
 *
 * Returns only meetups that carry at least one genuine EINUNDZWANZIG association
 * member via the meetup_user pivot and are visible on the map. Basis for
 * creating chat rooms in the Nostr client only for meetups with a genuine
 * association link. The gate logic lives in {@see VereinMeetupGate} (shared with
 * {@see MeetupMapController}).
 */
#[Group(name: 'Meetups', weight: 3)]
class VereinGatedMeetupController extends Controller
{
    public function __construct(private readonly VereinMeetupGate $gate) {}

    /**
     * Association-member-gated meetups.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(Request $request): Collection
    {
        return $this->gate->gatedMeetups($request->boolean('leaders_only'));
    }
}
