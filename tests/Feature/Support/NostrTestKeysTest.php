<?php

use App\Support\NostrEventVerifier;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;
use Tests\Fixtures\NostrTestKeys;

/**
 * A 62-character key as `Key::generatePrivateKey()` returns it for a scalar below 2^248
 * (about 1 draw in 256). Measured 2026-10-19: 73 of 20000 draws had this shape, never 63.
 */
const NOSTR_TEST_KEYS_SHORT_KEY = '3edae1b129d74d9c0734415d01bcaa230b7cdb68f07b2be0791f314b808781';

function nostrTestKeysSign(string $privateKey): array
{
    $event = new Event;
    $event->setKind(22242)->setCreatedAt(1_700_000_000)->setContent('')->setTags([]);

    (new Sign)->signEvent($event, $privateKey);

    return json_decode($event->toJson(), true, flags: JSON_THROW_ON_ERROR);
}

it('shows that the library signer rejects a key that lost its leading zero byte', function () {
    expect(strlen(NOSTR_TEST_KEYS_SHORT_KEY))->toBe(62);

    nostrTestKeysSign(NOSTR_TEST_KEYS_SHORT_KEY);
})->throws(InvalidArgumentException::class, 'Private key must be a 32-byte hex string');

it('pads a short key to 64 characters that signs and verifies', function () {
    $padded = NostrTestKeys::padPrivateKey(NOSTR_TEST_KEYS_SHORT_KEY);

    expect($padded)->toBe('00'.NOSTR_TEST_KEYS_SHORT_KEY);

    $signed = nostrTestKeysSign($padded);

    expect($signed['pubkey'])->toBe((new Key)->getPublicKey($padded))
        ->and(NostrEventVerifier::verify($signed))->toBeTrue();
});

it('never hands out a key the signer would reject', function () {
    foreach (range(1, 3000) as $ignored) {
        expect(NostrTestKeys::generatePrivateKey())->toMatch('/^[0-9a-f]{64}$/');
    }
});

it('leaves no test drawing a signing key straight from the library', function () {
    // Guard for the fix above: a new test that calls Key::generatePrivateKey() itself
    // is flaky again, on roughly 1 run in 256 per key, and passes on the rerun.
    $offenders = collect(Illuminate\Support\Facades\File::allFiles(base_path('tests')))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->reject(fn (SplFileInfo $file) => $file->getRealPath() === realpath(base_path('tests/Fixtures/NostrTestKeys.php')))
        ->filter(fn (SplFileInfo $file) => preg_match('/->generatePrivateKey\(/', file_get_contents($file->getRealPath())) === 1)
        ->map(fn (SplFileInfo $file) => str_replace(base_path().'/', '', $file->getRealPath()))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
