<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        return response()->json($this->profilePayload($request->user()));
    }

    /**
     * Profil aktualisieren
     *
     * Erlaubt dem Token-Inhaber, den eigenen Anzeigenamen zu ändern.
     * Rollen (is_lecturer/is_leader) sind bewusst NICHT änderbar.
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
            'is_leader' => (bool) $user->is_leader,
            'avatar' => $user->profile_photo_url,
        ];
    }
}
