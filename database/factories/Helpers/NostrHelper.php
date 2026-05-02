<?php

namespace Database\Factories\Helpers;

use Illuminate\Support\Str;

class NostrHelper
{
    /**
     * @return array<int, string>
     */
    public static function realNpubs(): array
    {
        return [
            'npub1sg6plzptd64u62a878hep2kev88swjh3tw00gjsfl8f237lmu63q0uf63m', // jack
            'npub1xtscya34g58tk0z605fvr788k263gsu6cy9x0mhnm87echrgufzsevkk5s', // saylor
            'npub1qny3tkh0acurzla8x3zy4nhrjz5zd8l9sy9jys09umwng00manysew95gx', // odell
            'npub1u8lnhlw5usp3t9vmpz60ejpyt649z33hu82wc2hpv6m5xdqmuxhs46turz', // gigi
            'npub1az9xj85cmxv8e9j9y80lvqp97crsqdu2fpu3srwthd99qfu9qsgstam8y8', // marty bent
            'npub1a2cww4kn9wqte4ry70vyfwqyqvpswksna27rtxd8vty6c74era8sdcw83a', // lyn alden
            'npub1a3hrd4wfawu0pkvm2nrqapcwjfa7gh23ymp7uksdg6flnv7c4ehq6dpcyu', // dergigi
            'npub1ev0eu6cy4dt5rrrju3xz77f8ww26pcnaty3xq5tlh2eheahuzxpsd2zrqp', // pleb
            'npub1mj8mcsdj3lp3xyamr0pwfwsnumv4w3z4yqfg8wpcrk6lqkx6n7ksz0n5z3', // gpt-3
            'npub1l2vyh47mk2p0qlsku7hg0vn29faehy9hy34ygaclpn66ukqp3afqutajft', // ODELL
            'npub10ghxwy0z9wq75e5ehl5xsymq5d28etr96ya5j6vz76m9z3l9j2sqphr8wm', // Hodl Hodl
            'npub16dhgpql60vmd4mnydjut87vla23a38j689jssaqlqqlzrtqtd0kqex4nf2', // Bitcoin Mechanic
            'npub1xy54p83r6wnpyhs52xjeztd7qyyeu9ghymz8v66yu8kt3jzx75rs2qvz8x', // Tomer
            'npub1nstrcu63lzpjkz94djajuz2evrgu2psd66cwgc0gz0c0qazezx0q9urg5l', // tooner
            'npub1zk6u7mxlflguqteghn8q7xtu47hyerruv6379c3z9w8s7ywqq02qrh40nr', // amboss
            'npub16f3y4ej62vca7s0wlf0kpzjn7zwx48xg6n4hph5e8mr8u9qrjnxstxn5lr', // dazbea
            'npub1xkym0yhu9hgwes2vchden6etqkrt5pja5l7w0ywqp7042at8638stslfsr', // dergigi
            'npub1y3yzfnkvkkdtv9j83fz9zk8jrxun7nzc8h3rnnwmnmlnxfdpqyxs9hu03l', // bitcoinmechanic
            'npub1u85ksjsrjsezndvr0vqnrr98c7l68k73stfp02n9d4lpr8t3ya3qz92sf2', // jack mallers
            'npub1nytmln3wdhzaa7y9zk83w6js5ydp4z2nm8r4vsewlmcrlnktwwzs7l40v0', // proof of work
        ];
    }

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

    public static function randomNpub(): string
    {
        if (random_int(1, 100) <= 70) {
            return self::realNpubs()[array_rand(self::realNpubs())];
        }

        return 'npub1'.Str::random(58);
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
