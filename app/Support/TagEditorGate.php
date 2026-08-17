<?php

namespace App\Support;

use App\Models\User;
use swentel\nostr\Key\Key;
use Throwable;

/**
 * Decides who may create tags outright, as opposed to merely suggesting one.
 *
 * The list lives in `config/einundzwanzig.tag_editors` as npubs and is seeded with
 * the board of Einundzwanzig e.V. Users authenticate through Nostr, so `users.nostr`
 * holds an npub — but callers may also hold the hex pubkey from a signed event, and
 * both encodings must give the same answer. Modelled on `App\Support\Board` in the
 * einundzwanzig-verein repository for exactly that reason.
 *
 * Fail-closed by design: an npub that cannot be decoded is dropped rather than passed
 * through, so a malformed config entry denies access instead of matching something
 * unintended. An empty list therefore denies everyone — never grants everyone.
 */
class TagEditorGate
{
    /**
     * Decoded hex pubkeys, keyed by the npub list they came from. Keying by the
     * source rather than memoising one value keeps the cache honest when the
     * configuration changes inside a single process (tests do this constantly).
     *
     * @var array<string, array<int, string>>
     */
    private static array $pubkeys = [];

    /**
     * The configured editor npubs.
     *
     * @return array<int, string>
     */
    public static function npubs(): array
    {
        return array_values(array_filter(
            (array) config('einundzwanzig.tag_editors', []),
            static fn ($npub): bool => is_string($npub) && $npub !== ''
        ));
    }

    /**
     * The configured editors as 64-character hex pubkeys.
     *
     * @return array<int, string>
     */
    public static function pubkeys(): array
    {
        $npubs = self::npubs();
        $cacheKey = implode(',', $npubs);

        if (isset(self::$pubkeys[$cacheKey])) {
            return self::$pubkeys[$cacheKey];
        }

        $key = new Key;
        $pubkeys = [];

        foreach ($npubs as $npub) {
            try {
                $pubkeys[] = mb_strtolower($key->convertToHex($npub));
            } catch (Throwable) {
                continue;
            }
        }

        return self::$pubkeys[$cacheKey] = $pubkeys;
    }

    public static function containsNpub(?string $npub): bool
    {
        return $npub !== null && $npub !== '' && in_array($npub, self::npubs(), true);
    }

    public static function containsPubkey(?string $pubkey): bool
    {
        return $pubkey !== null
            && $pubkey !== ''
            && in_array(mb_strtolower($pubkey), self::pubkeys(), true);
    }

    /**
     * Whether this user may create tags directly.
     *
     * `users.nostr` normally holds an npub, but a hex pubkey is accepted too so the
     * answer does not depend on which encoding happened to be stored.
     */
    public static function allows(?User $user): bool
    {
        $identity = $user?->nostr;

        if (! is_string($identity) || $identity === '') {
            return false;
        }

        return self::containsNpub($identity) || self::containsPubkey($identity);
    }

    /**
     * Drop the memoised hex lists.
     */
    public static function flush(): void
    {
        self::$pubkeys = [];
    }
}
