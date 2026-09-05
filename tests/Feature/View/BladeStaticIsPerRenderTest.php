<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Issue #99 — a `static` in a Blade view is per RENDER, not per request
|--------------------------------------------------------------------------
|
| #55 item 4 cost a page full of ambiguous selectors: the calendar-stream
| picker numbered its `data-testid` prefixes with a static counter, and both
| instances on meetups/landingpage.blade.php ended up as `calendar-stream-1`
| (14 ids, 7 distinct). The instance was fixed there; this file pins the
| CLASS, because nothing in the reading of a view says the static is broken.
|
| Two things are asserted, and they are different claims:
|
|  1. The Blade-level fact. One HTTP request that renders the same view twice
|     sees the static reset to 1 both times, while the request attribute the
|     picker now uses counts 1, 2. Without the second half the first half is
|     unfalsifiable — a view that never rendered would also report "1, 1".
|
|  2. The reason, isolated in plain PHP. The issue attributes the reset to the
|     fresh closure literal in Filesystem::getRequire(); measured on PHP 8.5.9
|     that is true but not the whole cause, and not the load-bearing one. A
|     `static` at the top level of an INCLUDED file does not survive the
|     include even when the including scope is a plain named function that is
|     never re-created. So caching the closure would not repair it, and the
|     property is not going to be tuned away by a framework change to
|     getRequire(). Ordinary statics still behave normally, which is exactly
|     why the defect reads as correct code.
|
*/

/**
 * Reads the two counters the fixture view prints.
 *
 * @return array{static: int, request: int}
 */
function staticLifetimeProbeCounters(string $html, int $occurrence): array
{
    preg_match_all('/data-testid="probe-static">(\d+)</', $html, $staticMatches);
    preg_match_all('/data-testid="probe-request">(\d+)</', $html, $requestMatches);

    expect(array_key_exists($occurrence, $staticMatches[1]))->toBeTrue(
        'The fixture view rendered fewer times than the test expected: found '
        .count($staticMatches[1]).' render(s) in the response.'
    );

    return [
        'static' => (int) $staticMatches[1][$occurrence],
        'request' => (int) $requestMatches[1][$occurrence],
    ];
}

beforeEach(function () {
    View::addNamespace('issue99', __DIR__.'/../../Fixtures/views');

    Route::get('/_test/issue-99-double-render', fn (): string => view('issue99::static-lifetime-probe')->render()
        .view('issue99::static-lifetime-probe')->render());
});

it('resets a static in a Blade view on every render inside one request', function () {
    $html = $this->get('/_test/issue-99-double-render')->assertSuccessful()->getContent();

    $first = staticLifetimeProbeCounters($html, 0);
    $second = staticLifetimeProbeCounters($html, 1);

    // The defect: the second render of the same view in the same request does
    // not see the first one. A counter written this way never leaves 1.
    expect($first['static'])->toBe(1)
        ->and($second['static'])->toBe(1);

    // The fix pattern from #55, measured on the same two renders. This is what
    // makes the assertion above mean something: the view really did render
    // twice, and request-scoped state really does count across the two.
    expect($first['request'])->toBe(1)
        ->and($second['request'])->toBe(2);
});

it('restarts the request-scoped counter on the next request', function () {
    // The counter has to be per request, not per PHP process — otherwise the
    // ids would keep climbing and a Livewire round trip would address nodes
    // that no longer exist. Both requests happen in this one process.
    $firstRequest = staticLifetimeProbeCounters(
        $this->get('/_test/issue-99-double-render')->assertSuccessful()->getContent(),
        0
    );

    $secondRequest = staticLifetimeProbeCounters(
        $this->get('/_test/issue-99-double-render')->assertSuccessful()->getContent(),
        0
    );

    expect($firstRequest['request'])->toBe(1)
        ->and($secondRequest['request'])->toBe(1);
});

it('loses a top-level static across an include even from a scope that is never re-created', function () {
    $path = tempnam(sys_get_temp_dir(), 'issue99').'.php';
    file_put_contents($path, '<?php static $n = 0; $n++; return $n;');

    try {
        // Not a closure literal: one named function, called three times. If the
        // fresh closure in getRequire() were the whole cause, this would count.
        $includedFromNamedFunction = [
            includeIssue99Probe($path),
            includeIssue99Probe($path),
            includeIssue99Probe($path),
        ];

        // A static that is written where PHP keeps it. The contrast is the
        // point: the language is not misbehaving, the include is the boundary.
        $declaredInNamedFunction = [
            issue99NativeStaticCounter(),
            issue99NativeStaticCounter(),
            issue99NativeStaticCounter(),
        ];

        expect($includedFromNamedFunction)->toBe([1, 1, 1])
            ->and($declaredInNamedFunction)->toBe([1, 2, 3]);
    } finally {
        @unlink($path);
    }
});

it('keeps resources/views free of static variables', function () {
    // The sweep #99 asked for, standing. It came back empty on 2026-09-05 —
    // #55's picker was the only instance — and an empty result is only worth
    // something if it stays empty, because the defect is invisible in review
    // and only shows up when a view is rendered twice in one request.
    $views = Finder::create()
        ->files()
        ->in(resource_path('views'))
        ->name('*.blade.php');

    $hits = [];

    foreach ($views as $view) {
        // `static fn` / `static function` are unbound closures, not state, and
        // are deliberately not matched. Only a static VARIABLE is the defect.
        if (preg_match('/\bstatic\s+\$/', $view->getContents()) === 1) {
            $hits[] = $view->getRelativePathname();
        }
    }

    expect($hits)->toBe([], implode("\n", [
        'A `static $` appeared in a Blade view. It is per render, not per request:',
        'the view is re-included on every render and the variable starts over, so it',
        'cannot count, cache or emit once. Use request()->attributes like',
        'resources/views/components/calendar-stream-picker.blade.php does, or state in',
        'a comment at the hit that per-render really is what you want. See issue #99.',
        'Hits: '.implode(', ', $hits),
    ]));
});

function includeIssue99Probe(string $path): int
{
    return require $path;
}

function issue99NativeStaticCounter(): int
{
    static $n = 0;

    return ++$n;
}
