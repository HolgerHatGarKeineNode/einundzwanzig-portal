<?php

use App\Support\RegionRoutes;

/*
 * Reine Logik ohne Framework-Zustand (statische Arrays, keine DB, kein HTTP) — deshalb
 * Unit statt Feature. tests/Feature/RegionRoutesTest.php prueft denselben Namen auf
 * Endpunkt-Ebene (404/200 ueber echte Requests), das hier ist die Klasse selbst.
 */
it('returns the plain and region-variant route for a known name (N5)', function () {
    expect(RegionRoutes::pair('meetups.index'))->toBe(['meetups.index', 'meetups.index-region'])
        ->and(RegionRoutes::pair('meetups.index-region'))->toBe(['meetups.index', 'meetups.index-region']);
});

it('returns null for a route without a region pair (N5)', function () {
    expect(RegionRoutes::pair('courses.index'))->toBeNull();
});

it('plain() resolves to the country-only route from either side of the pair (N5)', function () {
    expect(RegionRoutes::plain('meetups.index-region'))->toBe('meetups.index')
        ->and(RegionRoutes::plain('meetups.index'))->toBe('meetups.index');
});

it('plain() never guesses on an unknown route — it returns null (N5)', function () {
    // Darauf ist der Riegel in updatedCurrentCountry() angewiesen: nur wenn plain()
    // null liefert, bleibt eine Route wie courses.index unangetastet (N3).
    expect(RegionRoutes::plain('courses.index'))->toBeNull();
});

it('withRegion() resolves to the region variant from either side of the pair (N5)', function () {
    expect(RegionRoutes::withRegion('meetups.index'))->toBe('meetups.index-region')
        ->and(RegionRoutes::withRegion('meetups.index-region'))->toBe('meetups.index-region');
});

it('withRegion() returns null for a route without a region pair (N5)', function () {
    expect(RegionRoutes::withRegion('courses.index'))->toBeNull();
});

it('supports() is true only for routes with a region pair (N5)', function () {
    expect(RegionRoutes::supports('meetups.index'))->toBeTrue()
        ->and(RegionRoutes::supports('meetups.index-region'))->toBeTrue()
        ->and(RegionRoutes::supports('courses.index'))->toBeFalse();
});
