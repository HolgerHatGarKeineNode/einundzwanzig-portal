<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMeetupLeaderRequest;
use App\Models\Meetup;
use App\Models\User;
use App\Support\NostrLogin;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Leader-Delegation für Meetups: ein bestehender Leader (bzw. Ersteller/
 * Super-Admin) verwaltet die Leader eines Meetups (meetup_user.is_leader).
 * Leader dürfen die Stammdaten bearbeiten — siehe MeetupPolicy::update().
 */
#[Group(name: 'Meetups', weight: 3)]
class MeetupLeaderController extends Controller
{
    /**
     * Leader auflisten
     *
     * Liefert alle Leader eines Meetups (id, name, nostr, avatar, is_creator).
     * Nur für Leader des Meetups sichtbar.
     */
    #[Response(status: 403, description: 'Nur ein Leader darf die Leader-Liste sehen.')]
    public function index(Meetup $meetup): JsonResponse
    {
        Gate::authorize('manageLeaders', $meetup);

        return response()->json(['data' => $this->leaders($meetup)]);
    }

    /**
     * Leader einsetzen
     *
     * Setzt den Nutzer mit dem angegebenen npub als Leader ein. Existiert noch
     * kein Account für den npub, wird er angelegt (greift, sobald die Person sich
     * erstmals einloggt). Idempotent: ein bereits gesetzter Leader bleibt Leader.
     */
    #[Response(status: 403, description: 'Nur ein Leader darf weitere Leader einsetzen.')]
    #[Response(status: 422, description: 'Ungültiger npub.')]
    public function store(StoreMeetupLeaderRequest $request, Meetup $meetup): JsonResponse
    {
        $user = NostrLogin::findOrCreateUser($request->string('npub')->toString());

        $meetup->promoteLeader($user);

        return response()->json(['data' => $this->leaders($meetup)], HttpResponse::HTTP_CREATED);
    }

    /**
     * Leader entziehen
     *
     * Entzieht dem Nutzer die Leader-Rolle für dieses Meetup (Demote: bleibt
     * Mitglied in „Meine Meetups", darf aber nicht mehr bearbeiten). Der
     * Ersteller des Meetups kann nie entzogen werden.
     */
    #[Response(status: 403, description: 'Nur ein Leader darf entziehen; der Ersteller ist geschützt.')]
    public function destroy(Meetup $meetup, User $user): JsonResponse
    {
        Gate::authorize('manageLeaders', $meetup);

        abort_if($user->getKey() === $meetup->created_by, HttpResponse::HTTP_FORBIDDEN, __('Der Ersteller des Meetups kann nicht entzogen werden.'));

        $meetup->demoteLeader($user);

        return response()->json(['data' => $this->leaders($meetup)]);
    }

    /**
     * Leader-Liste als flaches Array (Ersteller zuerst).
     *
     * @return array<int, array<string, mixed>>
     */
    private function leaders(Meetup $meetup): array
    {
        return $meetup->leaders()
            ->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'nostr' => $user->nostr,
                'avatar' => $user->profile_photo_url,
                'is_creator' => $user->getKey() === $meetup->created_by,
            ])
            ->all();
    }
}
