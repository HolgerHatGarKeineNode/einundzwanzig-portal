<?php

use App\Models\User;
use App\Models\WebhookSubscription;

/*
|--------------------------------------------------------------------------
| Issue #98 — the "awaiting approval" badge, measured at the pixel
|--------------------------------------------------------------------------
|
| `flux:badge size="sm"` sets 12px at weight 500. That is not large text
| (WCAG 1.4.3 draws the line at 18pt, or 14pt bold), so the pair has to reach
| 4.5:1. The amber it used to carry measured 4.340:1 on the light page —
| amber-700 #BB4D00 on amber-400/25 composited to #FFEDBF. Dark mode was never
| the problem (4.764:1). Blue measures 7.347:1 light and 5.234:1 dark.
|
| Why this test screenshots instead of computing: the badge fill is an alpha
| colour (`bg-blue-400/20`), so the pair that reaches the eye exists only after
| the compositor has laid it over whatever ground the page provides. Computing
| it from the Tailwind tokens was measured 2026-09-05 to be ~9% off in dark
| mode. A class assertion would be even weaker — it would still pass if Flux
| retuned the token underneath us, which is exactly how issue #98 came to be
| filed after #55.
|
| Two independent decoders read the same PNG: PHP's GD (`imagecolorat`) and a
| canvas `getImageData()` readback inside the page. They have to agree, or the
| instrument, not the page, is what is being measured.
|
| The exact ratio is NOT pinned. The fill is a large flat area and comes back
| byte-exact, so that one is pinned; the text colour is recovered from the
| darkest pixel of an antialiased glyph, and a machine with different font
| hinting can legitimately land a shade off. What is pinned is the WCAG
| threshold plus a headroom bound well below the measured value, which still
| catches any move to a weaker colour, and a negative control that puts amber
| back and has to reproduce the failure.
|
| Theme switching here toggles `dark` on <html>, which is precisely what
| @fluxAppearance's own script does.
|
*/

/**
 * Most frequent colour in the PNG is the fill; the one furthest from it in
 * relative luminance is the glyph core. Antialiasing only ever produces
 * colours BETWEEN the two, so this cannot overstate the contrast.
 *
 * @return array{bg: string, fg: string, fgCount: int, ratio: float}
 */
function measureBadgeContrastFromPng(string $path): array
{
    $image = imagecreatefrompng($path);
    $width = imagesx($image);
    $height = imagesy($image);

    $histogram = [];
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $key = imagecolorat($image, $x, $y) & 0xFFFFFF;
            $histogram[$key] = ($histogram[$key] ?? 0) + 1;
        }
    }
    imagedestroy($image);
    arsort($histogram);

    $luminance = function (int $value): float {
        $channel = function (float $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(($value >> 16) & 255)
            + 0.7152 * $channel(($value >> 8) & 255)
            + 0.0722 * $channel($value & 255);
    };

    $background = array_key_first($histogram);
    $backgroundLuminance = $luminance($background);

    $foreground = $background;
    $largestDistance = 0.0;
    foreach ($histogram as $key => $count) {
        $distance = abs($luminance($key) - $backgroundLuminance);
        if ($distance > $largestDistance) {
            $largestDistance = $distance;
            $foreground = $key;
        }
    }

    $lighter = max($luminance($foreground), $backgroundLuminance);
    $darker = min($luminance($foreground), $backgroundLuminance);

    return [
        'bg' => sprintf('#%06X', $background),
        'fg' => sprintf('#%06X', $foreground),
        'fgCount' => $histogram[$foreground],
        'ratio' => round(($lighter + 0.05) / ($darker + 0.05), 3),
    ];
}

/** The same extraction rule, run by Chromium's PNG decoder instead of GD. */
const CANVAS_READBACK = <<<'JS'
(() => {
  const image = new Image();
  window.__canvasRead = null;
  image.onload = () => {
    const canvas = document.createElement('canvas');
    canvas.width = image.width;
    canvas.height = image.height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(image, 0, 0);
    const data = context.getImageData(0, 0, canvas.width, canvas.height).data;

    const histogram = new Map();
    for (let i = 0; i < data.length; i += 4) {
      const key = (data[i] << 16) | (data[i + 1] << 8) | data[i + 2];
      histogram.set(key, (histogram.get(key) || 0) + 1);
    }
    const entries = [...histogram.entries()].sort((a, b) => b[1] - a[1]);

    const channel = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    const luminance = (k) => 0.2126 * channel((k >> 16) & 255) + 0.7152 * channel((k >> 8) & 255) + 0.0722 * channel(k & 255);

    const background = entries[0][0];
    const backgroundLuminance = luminance(background);
    let foreground = background, largestDistance = 0;
    for (const [key] of entries) {
      const distance = Math.abs(luminance(key) - backgroundLuminance);
      if (distance > largestDistance) { largestDistance = distance; foreground = key; }
    }

    const hex = (k) => '#' + k.toString(16).toUpperCase().padStart(6, '0');
    const lighter = Math.max(luminance(foreground), backgroundLuminance);
    const darker = Math.min(luminance(foreground), backgroundLuminance);
    window.__canvasRead = {
      bg: hex(background),
      fg: hex(foreground),
      fgCount: histogram.get(foreground),
      ratio: Math.round(((lighter + 0.05) / (darker + 0.05)) * 1000) / 1000,
    };
  };
  image.src = 'data:image/png;base64,' + window.__pngBase64;
  return true;
})()
JS;

beforeEach(function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    WebhookSubscription::factory()->create([
        'user_id' => $owner->id,
        'url' => 'https://1.1.1.1/hooks/incoming',
        'approved_at' => null,
        'rejected_at' => null,
        'active' => true,
    ]);
});

/**
 * Screenshots the badge, then reads the same file back twice.
 *
 * @return array{gd: array{bg: string, fg: string, fgCount: int, ratio: float}, canvas: array<string, mixed>}
 */
function readBadgePixelsTwice(object $page, string $filename): array
{
    $page->screenshotElement('[data-testid="webhook-status-badge"]', $filename);
    $path = base_path('tests/Browser/Screenshots/'.$filename.'.png');

    $gd = measureBadgeContrastFromPng($path);

    $page->script('(() => { window.__pngBase64 = '.json_encode(base64_encode(file_get_contents($path))).'; return true; })()');
    $page->script(CANVAS_READBACK);
    $page->wait(0.4);

    return ['gd' => $gd, 'canvas' => $page->script('window.__canvasRead')];
}

function switchBadgeAppearance(object $page, string $theme): void
{
    $page->script('(() => { document.documentElement.classList.'.($theme === 'dark' ? 'add' : 'remove').'("dark"); void document.documentElement.offsetHeight; return true; })()');
    $page->wait(0.4);
}

it('renders the awaiting-approval badge above 4.5:1 on the light page', function () {
    $page = visit('/de/settings/webhooks');
    $page->resize(1280, 900)->wait(1.2);
    switchBadgeAppearance($page, 'light');

    $typography = $page->script('(() => { const b = document.querySelector("[data-testid=\'webhook-status-badge\']"); const s = getComputedStyle(b); return { status: b.dataset.status, fontSize: s.fontSize, fontWeight: s.fontWeight }; })()');

    // The threshold below is 4.5 and not 3.0 because of these two numbers:
    // 12px at weight 500 is not WCAG "large text".
    expect($typography['status'])->toBe('pending')
        ->and($typography['fontSize'])->toBe('12px')
        ->and($typography['fontWeight'])->toBe('500');

    $measured = readBadgePixelsTwice($page, 'issue-98-pending-badge-light');

    expect($measured['canvas'])->not->toBeNull()
        ->and($measured['canvas']['bg'])->toBe($measured['gd']['bg'])
        ->and($measured['canvas']['fg'])->toBe($measured['gd']['fg'])
        ->and($measured['canvas']['ratio'])->toBe($measured['gd']['ratio']);

    // Flat fill, byte-exact: blue-400/20 over the light page.
    expect($measured['gd']['bg'])->toBe('#DCECFF');

    // Enough fully covered glyph pixels that the darkest one is the text
    // colour itself and not a lone antialiased sample (735 measured).
    expect($measured['gd']['fgCount'])->toBeGreaterThan(100);

    // WCAG 1.4.3. Measured 7.347:1; the 6.5 floor is headroom for font
    // rendering, and still far above anything amber could reach.
    expect($measured['gd']['ratio'])->toBeGreaterThanOrEqual(4.5)
        ->and($measured['gd']['ratio'])->toBeGreaterThan(6.5);
});

it('renders the awaiting-approval badge above 4.5:1 on the dark page', function () {
    $page = visit('/de/settings/webhooks');
    $page->resize(1280, 900)->wait(1.2);
    switchBadgeAppearance($page, 'dark');

    $measured = readBadgePixelsTwice($page, 'issue-98-pending-badge-dark');

    expect($measured['canvas'])->not->toBeNull()
        ->and($measured['canvas']['bg'])->toBe($measured['gd']['bg'])
        ->and($measured['canvas']['fg'])->toBe($measured['gd']['fg'])
        ->and($measured['canvas']['ratio'])->toBe($measured['gd']['ratio']);

    // blue-400/40 over the dark page, which in this build is zinc-800.
    expect($measured['gd']['bg'])->toBe('#36577E');
    expect($measured['gd']['fgCount'])->toBeGreaterThan(100);

    // Measured 5.234:1; 4.9 is the floor, above amber's 4.764 so a silent
    // return to amber cannot slip through this one either.
    expect($measured['gd']['ratio'])->toBeGreaterThanOrEqual(4.5)
        ->and($measured['gd']['ratio'])->toBeGreaterThan(4.9);
});

/*
 * Negative control. Putting amber back on the live DOM has to reproduce the
 * reported failure — otherwise the two tests above are measuring something
 * that was never able to fail.
 */
it('reproduces the amber failure when the old colour is put back', function () {
    $page = visit('/de/settings/webhooks');
    $page->resize(1280, 900)->wait(1.2);
    switchBadgeAppearance($page, 'light');

    $page->script(<<<'JS'
    (() => {
      const badge = document.querySelector('[data-testid="webhook-status-badge"]');
      badge.classList.remove('text-blue-800', 'bg-blue-400/20');
      badge.classList.add('text-amber-700', 'bg-amber-400/25');
      void badge.offsetHeight;
      return badge.className;
    })()
    JS);
    $page->wait(0.3);

    $measured = readBadgePixelsTwice($page, 'issue-98-pending-badge-light-amber-control');

    expect($measured['canvas']['ratio'])->toBe($measured['gd']['ratio']);

    // The pair issue #98 reported: #BB4D00 on #FFEDBF.
    expect($measured['gd']['bg'])->toBe('#FFEDBF')
        ->and($measured['gd']['fg'])->toBe('#BB4D00')
        ->and($measured['gd']['ratio'])->toBeLessThan(4.5);
});

/*
|--------------------------------------------------------------------------
| Distinct from the three states it shares the page with
|--------------------------------------------------------------------------
|
| Contrast alone would not have stopped this: lime clears 4.5:1 too, and
| "awaiting approval" painted lime would pass every assertion above while
| telling the owner the opposite of the truth. Amber's real second failure was
| exactly there — against lime it is ΔE00 0.2 for a deuteranope, i.e. the same
| colour. So the guard is a perceptual distance, measured on the rendered
| pixels of all four badges and re-measured through the Viénot/Brettel/Mollon
| (1999) dichromat reduction.
|
*/
it('stays perceptually apart from active, paused and rejected, for dichromats too', function () {
    $owner = auth()->user();
    WebhookSubscription::query()->delete();

    $states = [
        'pending' => ['approved_at' => null, 'rejected_at' => null, 'active' => true],
        'active' => ['approved_at' => now(), 'rejected_at' => null, 'active' => true],
        'paused' => ['approved_at' => now(), 'rejected_at' => null, 'active' => false],
        'rejected' => ['approved_at' => null, 'rejected_at' => now(), 'active' => true],
    ];

    foreach ($states as $attributes) {
        WebhookSubscription::factory()->create(['user_id' => $owner->id] + $attributes);
    }

    $page = visit('/de/settings/webhooks');
    $page->resize(1280, 900)->wait(1.2);
    switchBadgeAppearance($page, 'light');

    $fills = [];
    foreach (array_keys($states) as $status) {
        $page->screenshotElement('[data-status="'.$status.'"]', 'issue-98-state-'.$status);
        $fills[$status] = measureBadgeContrastFromPng(base_path('tests/Browser/Screenshots/issue-98-state-'.$status.'.png'))['bg'];
    }

    // Four states on the page, four different fills.
    expect(array_unique($fills))->toHaveCount(4);

    foreach (['normal', 'protan', 'deutan'] as $vision) {
        foreach (['active', 'paused', 'rejected'] as $other) {
            $distance = perceptualDistanceDeltaE00($fills['pending'], $fills[$other], $vision);

            // ΔE00 of 5 is the point where two fills read as different colours
            // rather than two printings of one. Measured worst case for blue:
            // 9.1, against zinc. Amber against lime scored 0.2 here.
            expect([$vision, $other, $distance > 5.0])->toBe([$vision, $other, true]);
        }
    }
});

/** CIEDE2000 between two sRGB hex colours, optionally through a dichromat reduction. */
function perceptualDistanceDeltaE00(string $first, string $second, string $vision = 'normal'): float
{
    $toRgb = function (string $hex): array {
        $value = hexdec(ltrim($hex, '#'));

        return [($value >> 16) & 255, ($value >> 8) & 255, $value & 255];
    };

    $linear = fn (float $c): float => ($c /= 255) <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

    $encode = function (float $c): float {
        $c = max(0.0, min(1.0, $c));

        return 255 * ($c <= 0.0031308 ? 12.92 * $c : 1.055 * $c ** (1 / 2.4) - 0.055);
    };

    // Viénot, Brettel & Mollon (1999), applied to linear RGB.
    $reduce = function (array $rgb, string $type) use ($linear, $encode): array {
        [$r, $g, $b] = array_map($linear, $rgb);
        $matrix = $type === 'protan'
            ? [[0.11238, 0.88762, 0.0], [0.11238, 0.88762, 0.0], [0.00401, -0.00401, 1.0]]
            : [[0.29275, 0.70725, 0.0], [0.29275, 0.70725, 0.0], [-0.02234, 0.02234, 1.0]];

        return array_map(
            fn (array $row): float => $encode($row[0] * $r + $row[1] * $g + $row[2] * $b),
            $matrix
        );
    };

    $toLab = function (array $rgb) use ($linear): array {
        [$r, $g, $b] = array_map($linear, $rgb);
        $x = (0.4124564 * $r + 0.3575761 * $g + 0.1804375 * $b) / 0.95047;
        $y = 0.2126729 * $r + 0.7151522 * $g + 0.0721750 * $b;
        $z = (0.0193339 * $r + 0.1191920 * $g + 0.9503041 * $b) / 1.08883;
        $f = fn (float $t): float => $t > 0.008856 ? $t ** (1 / 3) : 7.787 * $t + 16 / 116;

        return [116 * $f($y) - 16, 500 * ($f($x) - $f($y)), 200 * ($f($y) - $f($z))];
    };

    $one = $toRgb($first);
    $two = $toRgb($second);
    if ($vision !== 'normal') {
        $one = $reduce($one, $vision);
        $two = $reduce($two, $vision);
    }

    [$l1, $a1, $b1] = $toLab($one);
    [$l2, $a2, $b2] = $toLab($two);

    $c1 = sqrt($a1 ** 2 + $b1 ** 2);
    $c2 = sqrt($a2 ** 2 + $b2 ** 2);
    $meanC = ($c1 + $c2) / 2;
    $g = 0.5 * (1 - sqrt($meanC ** 7 / ($meanC ** 7 + 25 ** 7)));
    $a1p = (1 + $g) * $a1;
    $a2p = (1 + $g) * $a2;
    $c1p = sqrt($a1p ** 2 + $b1 ** 2);
    $c2p = sqrt($a2p ** 2 + $b2 ** 2);
    $h1p = ($b1 == 0.0 && $a1p == 0.0) ? 0.0 : fmod(rad2deg(atan2($b1, $a1p)) + 360, 360);
    $h2p = ($b2 == 0.0 && $a2p == 0.0) ? 0.0 : fmod(rad2deg(atan2($b2, $a2p)) + 360, 360);

    $deltaL = $l2 - $l1;
    $deltaC = $c2p - $c1p;
    if ($c1p * $c2p == 0.0) {
        $deltah = 0.0;
    } elseif (abs($h2p - $h1p) <= 180) {
        $deltah = $h2p - $h1p;
    } else {
        $deltah = $h2p - $h1p + ($h2p - $h1p > 180 ? -360 : 360);
    }
    $deltaH = 2 * sqrt($c1p * $c2p) * sin(deg2rad($deltah / 2));

    $meanL = ($l1 + $l2) / 2;
    $meanCp = ($c1p + $c2p) / 2;
    if ($c1p * $c2p == 0.0) {
        $meanh = $h1p + $h2p;
    } elseif (abs($h1p - $h2p) <= 180) {
        $meanh = ($h1p + $h2p) / 2;
    } else {
        $meanh = ($h1p + $h2p + ($h1p + $h2p < 360 ? 360 : -360)) / 2;
    }

    $t = 1 - 0.17 * cos(deg2rad($meanh - 30)) + 0.24 * cos(deg2rad(2 * $meanh))
        + 0.32 * cos(deg2rad(3 * $meanh + 6)) - 0.20 * cos(deg2rad(4 * $meanh - 63));
    $sl = 1 + (0.015 * ($meanL - 50) ** 2) / sqrt(20 + ($meanL - 50) ** 2);
    $sc = 1 + 0.045 * $meanCp;
    $sh = 1 + 0.015 * $meanCp * $t;
    $rt = -sin(deg2rad(2 * (30 * exp(-((($meanh - 275) / 25) ** 2)))))
        * 2 * sqrt($meanCp ** 7 / ($meanCp ** 7 + 25 ** 7));

    return sqrt(
        ($deltaL / $sl) ** 2 + ($deltaC / $sc) ** 2 + ($deltaH / $sh) ** 2
        + $rt * ($deltaC / $sc) * ($deltaH / $sh)
    );
}
