<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use swentel\nostr\Event\Event as NostrEvent;
use swentel\nostr\Key\Key as NostrKey;

/**
 * Shared verification and user resolution for Nostr-based logins.
 *
 * Used by the interactive login component (challenge from the session,
 * signed via window.nostr) and by the mobile auth flow (challenge from
 * the k1 URL parameter, signed via a NIP-55 Android signer like Amber).
 */
final class NostrLogin
{
    public const CHALLENGE_TTL_SECONDS = 300;

    /**
     * Verify a NIP-42-style signed login event against an expected challenge
     * and return the signer's npub.
     *
     * Throws ValidationException on any invalid input — never trust client data.
     */
    public static function verifyEvent(mixed $signedEvent, string $expectedChallenge): string
    {
        if (! is_array($signedEvent)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $required = ['id', 'pubkey', 'created_at', 'kind', 'tags', 'content', 'sig'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $signedEvent)) {
                throw ValidationException::withMessages(['email' => __('auth.failed')]);
            }
        }

        if ((int) $signedEvent['kind'] !== 22242) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($expectedChallenge === '') {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $challengeFromEvent = null;
        foreach ($signedEvent['tags'] as $tag) {
            if (is_array($tag) && ($tag[0] ?? null) === 'challenge') {
                $challengeFromEvent = (string) ($tag[1] ?? '');
                break;
            }
        }

        if ($challengeFromEvent === null || ! hash_equals($expectedChallenge, $challengeFromEvent)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $createdAt = (int) $signedEvent['created_at'];
        if (abs(now()->timestamp - $createdAt) > self::CHALLENGE_TTL_SECONDS) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $eventJson = json_encode([
            'id' => (string) $signedEvent['id'],
            'pubkey' => (string) $signedEvent['pubkey'],
            'created_at' => $createdAt,
            'kind' => 22242,
            'tags' => $signedEvent['tags'],
            'content' => (string) $signedEvent['content'],
            'sig' => (string) $signedEvent['sig'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $isValid = false;
        try {
            $isValid = (new NostrEvent)->verify($eventJson);
        } catch (\Throwable) {
            $isValid = false;
        }

        if (! $isValid) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return (new NostrKey)->convertPublicKeyToBech32((string) $signedEvent['pubkey']);
    }

    /**
     * Find an existing user by npub or create a fresh account for it,
     * mirroring the LNURL auto-registration behaviour.
     */
    public static function findOrCreateUser(string $npub): User
    {
        $user = User::query()->where('nostr', $npub)->first();

        if ($user) {
            return $user;
        }

        return User::create([
            'public_key' => null,
            'is_lecturer' => true,
            'name' => str()->random(10),
            'email' => str($npub)->substr(-12).'@portal.einundzwanzig.space',
            'nostr' => $npub,
            'lnbits' => [
                'read_key' => null,
                'url' => null,
                'wallet_id' => null,
            ],
        ]);
    }
}
