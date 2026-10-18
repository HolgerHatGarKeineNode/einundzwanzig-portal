<?php

namespace Tests\Fixtures;

use swentel\nostr\Key\Key;

/**
 * Throwaway Nostr private keys that the signer accepts every time.
 *
 * `Key::generatePrivateKey()` returns elliptic's `toString('hex')`, which drops leading zero
 * bytes: a scalar below 2^248 (about 1 draw in 256) comes back as 62 hex characters, and
 * `SchnorrSigner::sign()` rejects anything but 64 ("Private key must be a 32-byte hex
 * string"). A test that signs with the raw value fails on roughly 1 key in 256 and passes
 * on the rerun. Always take the key from here.
 */
final class NostrTestKeys
{
    /**
     * A fresh private key, always 64 lower-case hex characters.
     */
    public static function generatePrivateKey(): string
    {
        return self::padPrivateKey((new Key)->generatePrivateKey());
    }

    /**
     * Left-pad a private key that lost its leading zero bytes back to 64 hex characters.
     */
    public static function padPrivateKey(string $hex): string
    {
        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }
}
