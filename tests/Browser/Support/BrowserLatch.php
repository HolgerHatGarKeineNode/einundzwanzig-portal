<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| THE BROWSER LATCH — every complaint a page makes, collected for every test
|--------------------------------------------------------------------------
|
| WHY THIS EXISTS: assertNoJavaScriptErrors() and assertNoConsoleLogs() in
| pest-plugin-browser see exactly two things (Playwright/InitScript.php: a
| wrapped console.log and a BUBBLING window 'error' listener). Not seen:
|
|   1. console.error / console.warn — where Livewire and Alpine report a dead
|      component or a broken expression;
|   2. unhandledrejection — a rejected promise nobody caught;
|   3. resource failures (a 404 on a <script>/<img>/<link>) — they fire on the
|      ELEMENT and do not bubble;
|   4. HTTP status codes — a 500 on a Livewire roundtrip is a RESOLVED fetch
|      promise with a status, which appears in no console channel at all;
|   5. (not a complaint of the page but of the suite) requests to any origin
|      other than the server under test — a CDN call makes a test depend on a
|      third party's mood.
|
| HOW: the browser tests run against the in-process server, whose responses are
| built by this app's own HTTP kernel. A global middleware, installed per test,
|
|   - records every response with status >= 400 (documents, Livewire updates,
|     assets alike — the server sees them all, so no fetch/XHR wrapper is
|     needed for the status codes), and
|   - injects a recorder <script> as the first child of <head> of every HTML
|     page, so it is in place before the first byte of application script runs
|     and catches what Alpine and Livewire throw while booting. The recorder
|     posts what it sees to /__latch, which the same middleware answers.
|
| This covers every page a test opens, whichever way it opens it (visit(),
| click-through, wire:navigate), and needs no change in an individual test; the
| afterEach in tests/Pest.php fails any browser test whose latch is not empty.
|
| It is a BLANKET, not an allow-list: every console level, every uncaught error,
| every rejection, every failed resource, every >= 400, every off-origin
| request. An allow-list of the messages seen while writing this would, by
| construction, miss the next one. A test that provokes one of them on purpose
| takes it out with drain() and asserts on it.
|
| LIMITS, named: an entry is delivered by a fetch from the page, which the
| in-process server answers while the test waits on the browser. A complaint
| raised after the test's last browser call, or in a page that is already
| gone, is not seen — which is also not seen by any assertion of the test.
| A complaint on a document without a <head> (a bare error page) is not
| recorded by the recorder, but its status code still is.
| tests/Browser/BrowserLatchTest.php proves each channel by effect.
*/
final class BrowserLatch
{
    public const ENDPOINT = '/__latch';

    /** @var array<int, array{channel: string, message: string}> */
    private static array $entries = [];

    /**
     * Idempotent: Kernel::pushMiddleware() ignores a class it already has.
     */
    public static function install(): void
    {
        self::$entries = [];

        app(Kernel::class)->pushMiddleware(self::class);

        OfflineBrowser::install();
    }

    /**
     * What has been recorded so far, without forgetting it.
     *
     * @return array<int, array{channel: string, message: string}>
     */
    public static function peek(): array
    {
        return self::$entries;
    }

    /**
     * Everything recorded since the last drain, and forgets it.
     *
     * @return array<int, array{channel: string, message: string}>
     */
    public static function drain(): array
    {
        $entries = self::$entries;
        self::$entries = [];

        return $entries;
    }

    /**
     * Fails with the whole list when anything was recorded.
     */
    public static function assertSilent(): void
    {
        $entries = self::drain();

        expect($entries)->toBe([], sprintf(
            'The browser complained %d time(s) during this test: %s',
            count($entries),
            implode(' | ', array_map(
                fn (array $entry): string => '['.$entry['channel'].'] '.$entry['message'],
                $entries,
            )),
        ));
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getPathInfo() === self::ENDPOINT) {
            $body = json_decode($request->getContent(), true);

            if (is_array($body) && isset($body['channel'], $body['message'])) {
                self::$entries[] = [
                    'channel' => (string) $body['channel'],
                    'message' => (string) $body['message'],
                ];
            }

            return response('', 204, ['Content-Length' => '0']);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            self::$entries[] = [
                'channel' => 'http',
                'message' => $response->getStatusCode().' '.$request->getMethod().' '.$request->getPathInfo(),
            ];
        }

        $content = $response->getContent();

        if (
            is_string($content)
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && preg_match('/<head(\s[^>]*)?>/i', $content, $match, PREG_OFFSET_CAPTURE) === 1
        ) {
            $at = $match[0][1] + strlen($match[0][0]);

            $response->setContent(substr($content, 0, $at).'<script>'.self::recorder().'</script>'.substr($content, $at));
        }

        return $response;
    }

    /**
     * The recorder, as it is injected before the page's first script.
     */
    public static function recorder(): string
    {
        $endpoint = self::ENDPOINT;

        return <<<JS
        (() => {
            // Livewire's wire:navigate merges a fetched document's <head> into the
            // live one; the recorder must not install itself twice.
            if (window.__latch) {
                return;
            }
            window.__latch = true;

            // The ORIGINAL fetch, captured before anything can wrap it: the sink
            // must neither be recorded as a request of the page nor loop.
            const originalFetch = window.fetch.bind(window);

            const record = (channel, message) => {
                try {
                    originalFetch('{$endpoint}', {
                        method: 'POST',
                        keepalive: true,
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ channel: channel, message: String(message).slice(0, 500) }),
                    }).catch(() => {});
                } catch (error) {
                    // A recorder that throws would become the complaint it reports.
                }
            };

            const describe = (value) => (value && value.stack) ? value.stack : String(value);

            for (const level of ['error', 'warn', 'log', 'info', 'debug', 'trace']) {
                const original = console[level];
                console[level] = function (...args) {
                    record('console.' + level, args.map(describe).join(' '));

                    return original.apply(console, args);
                };
            }

            // console.assert speaks only when its condition is FALSY.
            const originalAssert = console.assert;
            console.assert = function (condition, ...args) {
                if (!condition) {
                    record('console.assert', args.map(describe).join(' '));
                }

                return originalAssert.apply(console, [condition, ...args]);
            };

            // CAPTURE PHASE: a failed <script>/<img>/<link> fires 'error' on the
            // element and does not bubble. e.target tells the kinds apart: window
            // for a thrown error, an element for a resource.
            window.addEventListener('error', (event) => {
                const target = event.target;

                if (target && target !== window && target.tagName) {
                    record('resource', target.tagName + ' ' + (target.src || target.href || ''));

                    return;
                }

                record('uncaught', event.message || describe(event.error));
            }, true);

            window.addEventListener('unhandledrejection', (event) => {
                record('unhandledrejection', describe(event.reason));
            });

            // OFF-ORIGIN REQUESTS. `buffered` replays what loaded before the
            // observer was attached.
            new PerformanceObserver((list) => {
                for (const resource of list.getEntries()) {
                    try {
                        const url = new URL(resource.name);

                        if (url.protocol !== 'data:' && url.protocol !== 'blob:' && url.origin !== location.origin) {
                            record('external', resource.name);
                        }
                    } catch (error) {
                        record('external', 'unparseable resource: ' + resource.name);
                    }
                }
            }).observe({ type: 'resource', buffered: true });

            // A fetch that never got an answer has no status for the server to see.
            window.fetch = function (...args) {
                return originalFetch(...args).catch((error) => {
                    // A navigation that supersedes a request aborts it on purpose.
                    if (!error || error.name !== 'AbortError') {
                        record('network', describe(error));
                    }

                    throw error;
                });
            };
        })();
        JS;
    }
}
