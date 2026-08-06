<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Profile', weight: 8)]
class UserController extends Controller
{
    /**
     * Own profile
     *
     * Returns the profile of the authenticated user (token holder).
     * Called by the mobile app directly after login.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->profilePayload($request->user()))
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Update profile
     *
     * Allows the token holder to change their own display name.
     * Roles (is_lecturer/is_leader) are deliberately NOT changeable.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update(['name' => $validated['name']]);

        return response()->json($this->profilePayload($user->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nostr' => $user->nostr,
            'is_lecturer' => (bool) $user->is_lecturer,
            // The leader role is per meetup (meetup_user.is_leader); globally = is
            // the user leader of ANY meetup. Drives the role badge.
            'is_leader' => $user->meetups()->wherePivot('is_leader', true)->exists(),
            'avatar' => $user->profile_photo_url,
        ];
    }
}
