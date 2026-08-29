<?php

use App\Support\GeoHash;

it('matches the well-known Wikipedia reference geohash', function () {
    expect(GeoHash::encode(57.64911, 10.40744, 10))->toBe('u4pruydqqv');
});

it('truncates to the requested precision', function () {
    expect(GeoHash::encode(57.64911, 10.40744, 5))->toBe('u4pru');
    expect(GeoHash::encode(57.64911, 10.40744, 1))->toBe('u');
});

it('encodes the origin as the well-known "s0000..." hash', function () {
    expect(GeoHash::encode(0.0, 0.0, 5))->toBe('s0000');
});

it('is deterministic for the same coordinates', function () {
    expect(GeoHash::encode(52.5200, 13.4050, 5))
        ->toBe(GeoHash::encode(52.5200, 13.4050, 5));
});

it('produces different hashes for different locations', function () {
    $berlin = GeoHash::encode(52.5200, 13.4050, 5);
    $vienna = GeoHash::encode(48.2082, 16.3738, 5);

    expect($berlin)->not->toBe($vienna);
});
