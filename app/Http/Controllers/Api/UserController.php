<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Profil', weight: 8)]
class UserController extends Controller
{
    /**
     * Eigenes Profil
     *
     * Liefert das Profil des authentifizierten Nutzers (Token-Inhaber).
     * Wird von der Mobile App direkt nach dem Login aufgerufen.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nostr' => $user->nostr,
            'is_lecturer' => (bool) $user->is_lecturer,
            'is_leader' => (bool) $user->is_leader,
            'avatar' => $user->profile_photo_url,
        ]);
    }
}
