@php
    /*
     * Fixture for tests/Feature/View/BladeStaticIsPerRenderTest.php — NOT a
     * production view. It carries the two competing ways to number something
     * in a Blade view side by side so the test can measure the difference:
     *
     *  - `static`, which looks like it survives the render and does not;
     *  - the request attribute, which is the pattern #55 settled on.
     *
     * Deliberately left as the only `static $` under the repository's Blade
     * files. `git grep -n 'static $' resources/views/` is the sweep issue #99
     * asks for and must keep coming back empty; this file lives under tests/.
     */
    static $staticCount = 0;
    $staticCount++;

    $requestCount = (int) request()->attributes->get('static-lifetime-probe-count', 0) + 1;
    request()->attributes->set('static-lifetime-probe-count', $requestCount);
@endphp
<span data-testid="probe-static">{{ $staticCount }}</span>
<span data-testid="probe-request">{{ $requestCount }}</span>
