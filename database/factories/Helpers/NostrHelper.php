<?php

namespace Database\Factories\Helpers;

use Illuminate\Support\Str;
use swentel\nostr\Key\Key;

class NostrHelper
{
    /**
     * @return array<int, string>
     */
    public static function realLightningAddresses(): array
    {
        return [
            'gigi@walletofsatoshi.com',
            'tony@strike.me',
            'jack@cash.app',
            'odell@getalby.com',
            'marty@walletofsatoshi.com',
            'lyn@strike.me',
            'pleb@getalby.com',
            'satoshi@nakamoto.btc',
            'darth@walletofsatoshi.com',
            'orange@getalby.com',
        ];
    }

    /**
     * A valid but synthetic npub: bech32 over 32 random bytes.
     *
     * Factories never hand out a real person's key — such a value ends up in
     * screenshots posted publicly and next to made-up names. The fixed npubs the
     * Verein gate tests use can no longer collide with a factory user either.
     */
    public static function randomNpub(): string
    {
        return (new Key)->convertPublicKeyToBech32(bin2hex(random_bytes(32)));
    }

    public static function randomLightningAddress(?string $username = null): string
    {
        if ($username !== null) {
            $domain = collect(['getalby.com', 'walletofsatoshi.com', 'strike.me', 'lnbits.io', 'coincorner.io'])->random();

            return Str::slug($username).'@'.$domain;
        }

        return self::realLightningAddresses()[array_rand(self::realLightningAddresses())];
    }

    public static function fakeNostrEventStatus(): ?string
    {
        if (random_int(1, 100) <= 90) {
            return null;
        }

        $relays = ['wss://relay.damus.io', 'wss://nos.lol', 'wss://relay.nostr.band', 'wss://nostr.wine'];
        $eventId = bin2hex(random_bytes(32));

        return 'Sent event '.substr($eventId, 0, 16).'... to '.collect($relays)->random();
    }

    /**
     * Generate a hex pubkey (64 chars) used in Nostr filters.
     */
    public static function randomHexPubkey(): string
    {
        return bin2hex(random_bytes(32));
    }
}
