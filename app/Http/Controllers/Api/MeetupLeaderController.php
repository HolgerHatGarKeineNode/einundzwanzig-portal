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
 * Leader delegation for meetups: an existing leader (or creator/
 * super admin) manages the leaders of a meetup (meetup_user.is_leader).
 * Leaders may edit the master data — see MeetupPolicy::update().
 */
#[Group(name: 'Meetups', weight: 3)]
class MeetupLeaderController extends Controller
{
    /**
     * List leaders
     *
     * Returns all leaders of a meetup (id, name, nostr, avatar, is_creator).
     * Visible only to leaders of the meetup.
     */
    #[Response(status: 403, description: 'Only a leader may view the leader list.')]
    public function index(Meetup $meetup): JsonResponse
    {
        Gate::authorize('manageLeaders', $meetup);

        return response()->json(['data' => $this->leaders($meetup)]);
    }

    /**
     * Appoint leader
     *
     * Appoints the user with the given npub as leader. If no account exists for
     * the npub yet, it is created (takes effect as soon as the person signs in
     * for the first time). Idempotent: an already appointed leader stays a leader.
     */
    #[Response(status: 403, description: 'Only a leader may appoint further leaders; a meetup steward may not appoint themselves.')]
    #[Response(status: 422, description: 'Invalid npub.')]
    public function store(StoreMeetupLeaderRequest $request, Meetup $meetup): JsonResponse
    {
        $user = NostrLogin::findOrCreateUser($request->string('npub')->toString());

        Gate::authorize('appointLeader', [$meetup, $user]);

        $meetup->promoteLeader($user);

        return response()->json(['data' => $this->leaders($meetup)], HttpResponse::HTTP_CREATED);
    }

    /**
     * Revoke leader
     *
     * Revokes the user's leader role for this meetup (demote: stays a
     * member in "My Meetups", but may no longer edit). The
     * creator of the meetup can never be revoked.
     */
    #[Response(status: 403, description: 'Only a leader may revoke; the creator is protected.')]
    public function destroy(Meetup $meetup, User $user): JsonResponse
    {
        Gate::authorize('manageLeaders', $meetup);

        abort_if($user->getKey() === $meetup->created_by, HttpResponse::HTTP_FORBIDDEN, __('Der Ersteller des Meetups kann nicht entzogen werden.'));

        $meetup->demoteLeader($user);

        return response()->json(['data' => $this->leaders($meetup)]);
    }

    /**
     * Leader list as a flat array (creator first).
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
