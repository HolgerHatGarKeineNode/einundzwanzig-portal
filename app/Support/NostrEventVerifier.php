<?php

namespace App\Support;

use Mdanter\Ecc\Crypto\Signature\SchnorrSigner;
use Throwable;

/**
 * NIP-01 id and signature check for an event that arrived from a relay.
 *
 * ## Why not `swentel\nostr\Event\Event::verify()`
 *
 * That method recomputes the id with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`,
 * and since PHP 7.1 that flag set still writes U+2028 and U+2029 as six-character JSON
 * unicode escapes. NIP-01 serialises every character verbatim except the seven it names
 * (newline, double quote, backslash, carriage return, tab, backspace, form feed), so for
 * any event whose content or tags hold
 * a LINE SEPARATOR or PARAGRAPH SEPARATOR the library hashes different bytes than the
 * author signed, and rejects a valid event. Fail-closed, so it never admits a forgery —
 * it silently drops a genuine RSVP instead (plan risk R11).
 *
 * `JSON_UNESCAPED_LINE_TERMINATORS` restores the verbatim form; everything else about
 * PHP's encoding already matches NIP-01 (backspace and form feed as short escapes, other
 * control characters as lowercase four-digit unicode escapes, DEL and non-ASCII verbatim).
 *
 * No cryptography is implemented here: the id is a SHA-256 over the canonical
 * serialisation, and the BIP-340 check is the same `mdanter/ecc` Schnorr verifier
 * swentel/nostr-php itself calls.
 */
final class NostrEventVerifier
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    /**
     * @param  array<string, mixed>  $event  A decoded event: id, pubkey, created_at, kind, tags, content, sig.
     */
    public static function verify(array $event): bool
    {
        if (! self::hasValidShape($event)) {
            return false;
        }

        $serialised = json_encode(
            [0, $event['pubkey'], $event['created_at'], $event['kind'], $event['tags'], $event['content']],
            self::JSON_FLAGS,
        );

        if ($serialised === false || ! hash_equals(hash('sha256', $serialised), $event['id'])) {
            return false;
        }

        try {
            return (new SchnorrSigner)->verify($event['pubkey'], $event['sig'], $event['id']);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function hasValidShape(array $event): bool
    {
        foreach (['id' => 64, 'pubkey' => 64, 'sig' => 128] as $field => $length) {
            if (! is_string($event[$field] ?? null) || ! preg_match('/^[0-9a-f]{'.$length.'}$/', $event[$field])) {
                return false;
            }
        }

        if (! is_int($event['created_at'] ?? null) || $event['created_at'] < 0
            || ! is_int($event['kind'] ?? null) || $event['kind'] < 0
            || ! is_string($event['content'] ?? null)
            || ! is_array($event['tags'] ?? null) || ! array_is_list($event['tags'])) {
            return false;
        }

        foreach ($event['tags'] as $tag) {
            if (! is_array($tag) || ! array_is_list($tag)) {
                return false;
            }

            foreach ($tag as $value) {
                if (! is_string($value)) {
                    return false;
                }
            }
        }

        return true;
    }
}
