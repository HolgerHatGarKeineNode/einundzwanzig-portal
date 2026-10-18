<?php

use Database\Factories\Helpers\NostrHelper;
use swentel\nostr\Key\Key;

/**
 * Factory npubs must be real bech32: lower case, checksummed, a 32-byte key.
 * They used to come from a list of named people's keys, 12 of 20 with a broken
 * checksum, or from `npub1` plus 58 random mixed-case characters.
 */
it('only produces valid npubs', function () {
    $key = new Key;

    foreach (range(1, 200) as $ignored) {
        $npub = NostrHelper::randomNpub();

        expect($npub)->toMatch('/^npub1[02-9ac-hj-np-z]{58}$/')
            ->and($key->convertToHex($npub))->toMatch('/^[0-9a-f]{64}$/');
    }
});
