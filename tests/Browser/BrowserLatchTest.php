<?php

use Tests\Browser\Support\BrowserLatch;

/*
|--------------------------------------------------------------------------
| The browser latch, proven by effect
|--------------------------------------------------------------------------
|
| tests/Pest.php fails every browser test whose latch is not empty after it. A
| guard that "never fires" is worth nothing until something provokes it, so each
| channel gets a positive control here: the complaint is injected on a real
| page, and the latch has to report it. The silent page is the negative control:
| without it a latch that reports everything (or a recorder that never
| installed) would pass the cases below as well.
|
| The controls take their entries out with drain(), which is also what keeps
| the afterEach from failing them.
*/

/**
 * Waits for the latch to hold an entry of the given channel by round-tripping
 * through the page, which is when the in-process server answers the recorder's
 * posts. A poll on the artifact, not a sleep.
 */
function awaitLatchEntry(object $page, string $channel): void
{
    for ($attempt = 0; $attempt < 100; $attempt++) {
        foreach (BrowserLatch::peek() as $entry) {
            if ($entry['channel'] === $channel) {
                return;
            }
        }

        $page->script('new Promise((resolve) => requestAnimationFrame(() => resolve(true)))');
    }
}

/**
 * @return array<int, string>
 */
function latchChannels(): array
{
    return array_values(array_unique(array_column(BrowserLatch::drain(), 'channel')));
}

it('is installed on the page and silent on a page that has nothing to say', function () {
    $page = visit('/welcome');

    expect($page->script('window.__latch === true'))->toBeTrue();

    // A second trip through the page lets any pending post reach the server.
    $page->script('new Promise((resolve) => requestAnimationFrame(() => resolve(true)))');

    expect(BrowserLatch::drain())->toBe([]);
});

it('reports an uncaught error', function () {
    $page = visit('/welcome');

    $page->script('setTimeout(() => { throw new Error("latch-probe-uncaught"); }, 0); true');
    awaitLatchEntry($page, 'uncaught');

    $entries = BrowserLatch::drain();

    expect(array_column($entries, 'channel'))->toContain('uncaught')
        ->and(implode(' ', array_column($entries, 'message')))->toContain('latch-probe-uncaught');
});

it('reports console.error and console.warn, which assertNoJavaScriptErrors cannot see', function () {
    $page = visit('/welcome');

    $page->script('console.error("latch-probe-error"); console.warn("latch-probe-warn"); true');
    awaitLatchEntry($page, 'console.warn');

    expect(latchChannels())->toContain('console.error')->toContain('console.warn');
});

it('reports a rejected promise nobody caught', function () {
    $page = visit('/welcome');

    $page->script('Promise.reject(new Error("latch-probe-rejection")); true');
    awaitLatchEntry($page, 'unhandledrejection');

    expect(latchChannels())->toContain('unhandledrejection');
});

it('reports a script that failed to load, on the page and as a 404 on the server', function () {
    $page = visit('/welcome');

    $page->script('const s = document.createElement("script"); s.src = "/latch-probe-missing.js"; document.head.appendChild(s); true');
    awaitLatchEntry($page, 'resource');

    $entries = BrowserLatch::drain();
    $channels = array_column($entries, 'channel');

    expect($channels)->toContain('resource')->toContain('http')
        ->and(implode(' ', array_column($entries, 'message')))->toContain('404 GET /latch-probe-missing.js');
});

it('reports a Livewire roundtrip the server answers with an error status', function () {
    $page = visit('/welcome');

    // Calling a method the component does not have: the roundtrip resolves with
    // an error status, the way a 500 does, and is a settled promise rather than
    // a console message.
    $page->script('(async () => { try { await Livewire.first().call("latchProbeNoSuchMethod"); } catch (error) {} return true; })()');
    awaitLatchEntry($page, 'http');

    $http = array_values(array_filter(BrowserLatch::drain(), fn (array $entry): bool => $entry['channel'] === 'http'));

    expect($http)->not->toBeEmpty()
        ->and($http[0]['message'])->toMatch('/^[45]\d\d POST \/livewire/');
});

it('reports a request to another origin', function () {
    $page = visit('/welcome');

    $page->script('new Image().src = "https://latch-probe.invalid/pixel.png"; true');
    awaitLatchEntry($page, 'external');

    $entries = BrowserLatch::drain();

    expect(array_column($entries, 'channel'))->toContain('external')
        ->and(implode(' ', array_column($entries, 'message')))->toContain('https://latch-probe.invalid/pixel.png');
});

it('answers third-party images locally, so the pages that carry them stay silent', function () {
    // The welcome page hard-codes a background image on images.unsplash.com.
    $page = visit('/welcome');

    $hosts = $page->script('Array.from(new Set(performance.getEntriesByType("resource").map((entry) => new URL(entry.name).host)))');

    $page->script('new Promise((resolve) => requestAnimationFrame(() => resolve(true)))');

    $origin = parse_url($page->script('location.origin'));

    expect($hosts)->not->toBeEmpty()
        ->and($hosts)->each->toBe($origin['host'].':'.$origin['port'])
        ->and($page->script('getComputedStyle(document.querySelector("[style*=background-image]")).backgroundImage'))->toContain('/__offline/img')
        ->and(BrowserLatch::drain())->toBe([]);
});
