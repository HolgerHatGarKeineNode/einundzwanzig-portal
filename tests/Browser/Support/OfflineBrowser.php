<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| NO BROWSER TEST LEAVES THE MACHINE — third-party images and fonts, answered locally
|--------------------------------------------------------------------------
|
| Measured with the BrowserLatch's `external` channel on SmokeTest and
| AuthFlowTest (2026-10-24), pages loaded by the browser suite reach for:
|
|   - https://tile.openstreetmap.de — the Leaflet tiles of every map page;
|   - https://127.0.0.1:<port>/favicon.ico — config/seo.php uses secure_url(),
|     so the page asks the test server for https on a port that speaks http;
|   - https://dergigi.com, https://images.unsplash.com and
|     https://ui-avatars.com — image URLs that factories and avatar defaults
|     put into <img src>;
|   - http://localhost:8000/storage/fonts/…woff2 — the Inconsolata files that
|     @googlefonts inlines. The URL is built from APP_URL, which is not the
|     in-process test server. Whether the font loads therefore depended on
|     whether somebody's dev server happened to listen on :8000, and when none
|     did (the normal case) every page load logged 32 failed LINK/resource
|     entries and rendered in the monospace fallback.
|
| A suite whose layout numbers depend on an unrelated process, or on a stranger's
| image host, is not deterministic. (The layout also used to load
| window.nostr.js from cdn.jsdelivr.net until 8b7e34e; that script changed
| the scroll container of every page, see WebhookApprovalLayoutTest.)
|
| THE FIX IS AT THE SERVER, because that is the only lever: the plugin launches
| Chromium with a fixed options object and its Context offers no route(). The
| in-process server answers through the app's own HTTP kernel, so a global
| middleware (installed per test by BrowserLatch::install()) rewrites those URLs
| to this server's own /__offline/ prefix and answers it:
|
|   - <img src="https://elsewhere/…"> and url(https://elsewhere/…) in CSS become
|     /__offline/img, a 1x1 PNG (the welcome and login pages hard-code
|     background images on dergigi.com and unsplash.com);
|   - the Leaflet tile URL (tile.openstreetmap.de, three views) becomes
|     /__offline/tile/, the same pixel;
|   - https://<this server>/ (what secure_url() makes of the favicon) becomes
|     http://<this server>/, because the test server does not speak TLS;
|   - the public disk's /fonts/ URL becomes /__offline/fonts/, an empty body
|     that no font decoder accepts. That is exactly what the fallback state
|     was before (the font never loaded), now reached without a network error,
|     and identical on every machine.
|
| WHAT THIS CHANGES ABOUT WHAT IS MEASURED, stated rather than left to be
| found: pages render in the fallback monospace, never in Inconsolata. Layout
| assertions that depend on glyph widths are measured in that font, as they were
| on every machine without a server on :8000.
|
| IT IS NOT AN ALLOW-LIST FOR THE NEXT HOST. Only <img src>, CSS url() and the fonts
| URL are rewritten. Any other off-origin request is reported by the latch on its
| `external` channel, so a new CDN link makes the suite red instead of quietly
| making it slow.
*/
final class OfflineBrowser
{
    public const PREFIX = '/__offline';

    /** 1x1 transparent PNG. */
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * Idempotent: Kernel::pushMiddleware() ignores a class it already has.
     */
    public static function install(): void
    {
        app(Kernel::class)->pushMiddleware(self::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (str_starts_with($path, self::PREFIX.'/img') || str_starts_with($path, self::PREFIX.'/tile/')) {
            return response(base64_decode(self::PIXEL), 200, ['Content-Type' => 'image/png']);
        }

        if (str_starts_with($path, self::PREFIX.'/fonts/')) {
            return response('', 200, ['Content-Type' => 'font/woff2']);
        }

        $response = $next($request);

        $type = (string) $response->headers->get('Content-Type');
        $content = $response->getContent();

        if (! is_string($content) || ! (str_contains($type, 'text/html') || str_contains($type, 'application/json'))) {
            return $response;
        }

        $origin = $request->getSchemeAndHttpHost();
        $fontsUrl = rtrim((string) config('filesystems.disks.public.url'), '/').'/fonts/';
        $escapedFontsUrl = str_replace('/', '\/', $fontsUrl);

        $rewritten = str_replace(
            [$fontsUrl, $escapedFontsUrl],
            [self::PREFIX.'/fonts/', str_replace('/', '\/', self::PREFIX).'\/fonts\/'],
            $content,
        );

        // The three Leaflet maps name the OpenStreetMap tile server inline.
        $rewritten = str_replace(
            ['https://tile.openstreetmap.de/', 'https:\/\/tile.openstreetmap.de\/'],
            [self::PREFIX.'/tile/', str_replace('/', '\/', self::PREFIX).'\/tile\/'],
            $rewritten,
        );

        // config/seo.php builds the favicon with secure_url(), so the page asks
        // THIS server for https://127.0.0.1:port/favicon.ico. The test server
        // speaks plain http; the request fails and the latch calls it external.
        $rewritten = str_replace('https://'.$request->getHttpHost().'/', $origin.'/', $rewritten);

        // Livewire answers with the component's HTML inside JSON, where the
        // slashes are escaped and the quotes carry a backslash; one pattern
        // covers both shapes.
        $rewritten = preg_replace_callback(
            '/(<img\b[^>]*?\bsrc=\\\\?["\'])(https?:.*?)(?=\\\\?["\'])/i',
            function (array $match) use ($origin): string {
                $url = stripslashes($match[2]);

                if (str_starts_with($url, $origin)) {
                    return $match[0];
                }

                return $match[1].(str_contains($match[2], '\/') ? '\/__offline\/img' : self::PREFIX.'/img');
            },
            $rewritten,
        ) ?? $rewritten;

        // Hard-coded background images on the welcome and login pages.
        $rewritten = preg_replace_callback(
            '/url\((["\']?)(https?:[^)"\']+)\1\)/i',
            fn (array $match): string => str_starts_with($match[2], $origin)
                ? $match[0]
                : 'url('.$match[1].self::PREFIX.'/img'.$match[1].')',
            $rewritten,
        ) ?? $rewritten;

        // A preload of a font that is deliberately empty would fire an error
        // event on its <link>; the @font-face rule carries the same URL and
        // falls back silently.
        $rewritten = preg_replace(
            '/<link\b[^>]*\brel=["\']preload["\'][^>]*'.preg_quote(self::PREFIX, '/').'\/fonts\/[^>]*>/i',
            '',
            $rewritten,
        ) ?? $rewritten;

        if ($rewritten !== $content) {
            $response->setContent($rewritten);
        }

        return $response;
    }
}
