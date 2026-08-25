<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
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

    /** Cache prefix for server-issued single-use login challenges (k1) of the token exchange. */
    private const CHALLENGE_CACHE_PREFIX = 'mobile:nostr:k1:';

    /**
     * Issue a fresh single-use login challenge (k1) and remember it server-side.
     *
     * The token-minting endpoints only accept a k1 that was issued here and
     * consume it exactly once — this is the replay protection the stateless
     * token exchange otherwise lacks (the interactive web login already binds
     * its challenge to the session).
     */
    public static function issueChallenge(): string
    {
        $k1 = bin2hex(random_bytes(32));
        Cache::put(self::CHALLENGE_CACHE_PREFIX.$k1, true, self::CHALLENGE_TTL_SECONDS);

        return $k1;
    }

    /**
     * Consume a previously issued challenge. Returns true exactly once per
     * issued k1 (single-use via Cache::pull); false for unknown, expired or
     * already-used k1 — which blocks replay of a captured (k1, event) pair.
     */
    public static function consumeChallenge(string $k1): bool
    {
        return Cache::pull(self::CHALLENGE_CACHE_PREFIX.$k1) === true;
    }

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

        /*
         * Kein `lnbits` mehr (P6) — dieselbe Begruendung wie im LNURL-Zwilling
         * ({@see \App\Http\Controllers\LnurlAuthController::findOrCreateUser()}): ein
         * Null-Objekt ohne Schreiber und ohne Leser, dessen Spalte gleich faellt. Beide
         * Anlagepfade mussten im selben Schritt weg; nur einen zu raeumen haette die
         * Neuanmeldung ueber den anderen Weg nach der Migration zerbrochen.
         */
        return User::create([
            'public_key' => null,
            'is_lecturer' => true,
            'name' => str()->random(10),
            'email' => str($npub)->substr(-12).'@portal.einundzwanzig.space',
            'nostr' => $npub,
        ]);
    }
}
