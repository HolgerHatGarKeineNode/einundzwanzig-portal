<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\VereinMeetupGate;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Vereinsmitglied-gegatete Meetup-Liste (Server-zu-Server, Bearer-Auth).
 *
 * Liefert nur Meetups, die über die meetup_user-Pivot mindestens ein echtes
 * EINUNDZWANZIG-Vereinsmitglied führen und auf der Karte sichtbar sind. Grundlage
 * dafür, im Nostr-Client nur Meetups mit echtem Vereinsbezug als Chat-Räume
 * anzulegen. Die Gate-Logik liegt in {@see VereinMeetupGate} (geteilt mit
 * {@see MeetupMapController}).
 */
#[Group(name: 'Meetups', weight: 3)]
class VereinGatedMeetupController extends Controller
{
    public function __construct(private readonly VereinMeetupGate $gate) {}

    /**
     * Vereinsmitglied-gegatete Meetups.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function __invoke(Request $request): Collection
    {
        return $this->gate->gatedMeetups($request->boolean('leaders_only'));
    }
}
