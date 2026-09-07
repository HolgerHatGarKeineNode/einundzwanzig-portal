<?php

/*
|--------------------------------------------------------------------------
| Pixel-level contrast probe
|--------------------------------------------------------------------------
|
| Shared by the browser tests that have to answer "what ratio actually reaches
| the eye here", rather than "what do the tokens say". The distinction is not
| pedantry: `opacity` and alpha fills are resolved by the compositor, so the
| pair that lands on the screen exists nowhere in the CSS. Computing it from
| Tailwind tokens was measured on this repository to be ~9% off in dark mode.
|
| This file is deliberately NOT named `*Test.php`. phpunit.xml declares the
| Browser suite as a bare <directory>, which uses PHPUnit's default `Test.php`
| suffix, so nothing here is collected as a test. It is pulled in with
| require_once by the files that use it.
|
| Three habits are copied from tests/Browser/Services/ServiceTypeFilterChipContrastTest.php,
| and none of them is decoration:
|
|   1. TWO DECODERS. The same PNG is read by PHP's GD and by a canvas
|      getImageData() readback inside the page. They have to agree, or what is
|      being measured is the instrument and not the page.
|   2. THE POINTER IS PARKED. screenshotElement() does not move the mouse, so
|      an element left under the cursor by an earlier click is photographed in
|      its :hover state. Measured while writing the chip test: 5.827:1 where
|      4.093:1 was correct.
|   3. A NEGATIVE CONTROL. Every caller has to put the defect back and watch
|      the probe report it. `pixelForceOpacity()` exists for that, and it
|      returns the computed opacity so the caller can prove the injection took
|      effect instead of assuming it did.
|
*/

/** WCAG 2.1 relative luminance of a packed 0xRRGGBB value. */
function pixelRelativeLuminance(int $rgb): float
{
    $channel = function (float $c): float {
        $c /= 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * $channel(($rgb >> 16) & 255)
        + 0.7152 * $channel(($rgb >> 8) & 255)
        + 0.0722 * $channel($rgb & 255);
}

function pixelContrastRatio(int $first, int $second): float
{
    $lighter = max(pixelRelativeLuminance($first), pixelRelativeLuminance($second));
    $darker = min(pixelRelativeLuminance($first), pixelRelativeLuminance($second));

    return round(($lighter + 0.05) / ($darker + 0.05), 3);
}

/**
 * The threshold WCAG 1.4.3 puts on a given rendering. 18.66px is the CSS-pixel
 * equivalent of 14pt, 24px of 18pt — the two sizes the success criterion names.
 */
function pixelTextThreshold(float $fontSizePx, int $fontWeight): float
{
    $isLarge = $fontSizePx >= 24.0 || ($fontSizePx >= 18.66 && $fontWeight >= 700);

    return $isLarge ? 3.0 : 4.5;
}

/**
 * Most frequent colour in the PNG is the ground; the colour furthest from it in
 * relative luminance is the glyph core. Antialiasing only ever produces colours
 * BETWEEN the two, so this cannot overstate the contrast.
 *
 * @return array{bg: string, fg: string, fgCount: int, bgCount: int, ratio: float, width: int, height: int}
 */
function pixelReadPng(string $path): array
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

    $background = array_key_first($histogram);
    $backgroundLuminance = pixelRelativeLuminance($background);

    $foreground = $background;
    $largestDistance = 0.0;
    foreach ($histogram as $key => $count) {
        $distance = abs(pixelRelativeLuminance($key) - $backgroundLuminance);
        if ($distance > $largestDistance) {
            $largestDistance = $distance;
            $foreground = $key;
        }
    }

    return [
        'bg' => sprintf('#%06X', $background),
        'fg' => sprintf('#%06X', $foreground),
        'fgCount' => $histogram[$foreground],
        'bgCount' => $histogram[$background],
        'ratio' => pixelContrastRatio($foreground, $background),
        'width' => $width,
        'height' => $height,
    ];
}

/** The same extraction rule, run by Chromium's PNG decoder instead of GD. */
const PIXEL_CANVAS_READBACK = <<<'JS'
(() => {
  const image = new Image();
  window.__pixelRead = null;
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
    const ratio = (a, b) => {
      const hi = Math.max(luminance(a), luminance(b));
      const lo = Math.min(luminance(a), luminance(b));
      return Math.round(((hi + 0.05) / (lo + 0.05)) * 1000) / 1000;
    };

    const background = entries[0][0];
    const backgroundLuminance = luminance(background);
    let foreground = background, largestDistance = 0;
    for (const [key] of entries) {
      const distance = Math.abs(luminance(key) - backgroundLuminance);
      if (distance > largestDistance) { largestDistance = distance; foreground = key; }
    }

    const hex = (k) => '#' + k.toString(16).toUpperCase().padStart(6, '0');
    window.__pixelRead = {
      bg: hex(background),
      fg: hex(foreground),
      fgCount: histogram.get(foreground),
      bgCount: histogram.get(background),
      ratio: ratio(foreground, background),
      width: canvas.width,
      height: canvas.height,
    };
  };
  image.src = 'data:image/png;base64,' + window.__pixelPngBase64;
  return true;
})()
JS;

/**
 * Moves the real pointer away from anything the test has been clicking.
 * Without this the probe photographs a :hover rendering and reports a ratio the
 * page never shows at rest.
 *
 * It parks on a dot the probe injects rather than on a page element, because
 * every page probed here has a different heading and `body` is not a hover
 * target Playwright will accept (measured: "Timeout 5000ms exceeded"). The dot
 * is `position: fixed`, so it changes no layout, and it is the topmost hit
 * target at that corner, so nothing underneath receives :hover either.
 */
function pixelParkPointer(object $page): void
{
    $page->script(<<<'JS'
    (() => {
      const old = document.getElementById('pixel-pointer-park');
      if (old) { old.remove(); }

      // An open <dialog> lives in the top layer, which no z-index on a
      // body-level element can reach: Playwright then reports the dot as
      // covered and hover() times out. Park inside the dialog instead.
      const host = document.querySelector('dialog[open]') || document.body;

      const dot = document.createElement('div');
      dot.id = 'pixel-pointer-park';
      dot.style.cssText = 'position:fixed;top:0;left:0;width:6px;height:6px;z-index:2147483647;background:transparent';
      host.appendChild(dot);
      return host.tagName;
    })()
    JS);
    $page->hover('#pixel-pointer-park')->wait(0.2);
}

/** Flips the documentElement between the two themes and lets the paint settle. */
function pixelSwitchTheme(object $page, string $theme): void
{
    $page->script('(() => { document.documentElement.classList.'.($theme === 'dark' ? 'add' : 'remove').'("dark"); void document.documentElement.offsetHeight; return true; })()');
    $page->wait(0.4);
}

/**
 * Puts the reported defect back onto a live element and reports the opacity the
 * browser actually computed. A control that fails to apply is worse than no
 * control: it reports "no failure" for the wrong reason.
 *
 * An inline style rather than the utility class, because Tailwind only emits a
 * class some source file still uses — and the whole point of the fix is that no
 * source file uses these any more. `opacity-60` and `style.opacity = '0.6'`
 * compute to the identical value, which is what the return value proves.
 */
function pixelForceOpacity(object $page, string $selector, string $value): string
{
    return (string) $page->script(
        '(() => { const e = document.querySelector('.json_encode($selector).'); e.style.opacity = '.json_encode($value).'; void e.offsetHeight; return getComputedStyle(e).opacity; })()'
    );
}

/**
 * Strips utility classes from every element matching a selector, so a control
 * can put a site back into its pre-fix rendering rather than into an
 * approximation of it.
 *
 * Needed wherever the fix did two things at once. Fading a line that has since
 * been given a named muted colour measures the NEW colour under the OLD fade —
 * a number the page never showed anybody. The reported defect was the old
 * colour under the old fade, and that is what a control has to reproduce.
 *
 * Returns the computed colour afterwards, so the caller can see the class
 * removal took effect instead of assuming it.
 */
function pixelStripClasses(object $page, string $selector, array $classes): string
{
    return (string) $page->script(
        '(() => { const c = '.json_encode($classes).'; let last = "";
          document.querySelectorAll('.json_encode($selector).').forEach((e) => { c.forEach((k) => e.classList.remove(k)); last = getComputedStyle(e).color; });
          return last; })()'
    );
}

function pixelClearOpacity(object $page, string $selector): void
{
    $page->script('(() => { const e = document.querySelector('.json_encode($selector).'); e.style.opacity = ""; void e.offsetHeight; return true; })()');
    $page->wait(0.2);
}

/**
 * Screenshots one element and reads the file back with both decoders.
 *
 * `fontSize`/`fontWeight` come off the live element so the caller never has to
 * assume which WCAG threshold applies, and `opacity` is read so a stray fade
 * anywhere up the ancestor chain shows up in the record instead of silently
 * moving the number.
 *
 * @return array{gd: array<string, mixed>, canvas: array<string, mixed>, css: array<string, mixed>}
 */
function pixelMeasureText(object $page, string $selector, string $filename): array
{
    $css = $page->script('(() => {
        const all = document.querySelectorAll('.json_encode($selector).');
        const e = all[0];
        if (! e) { return null; }
        const s = getComputedStyle(e);
        const r = e.getBoundingClientRect();
        let effective = 1;
        for (let n = e; n && n.nodeType === 1; n = n.parentElement) { effective *= parseFloat(getComputedStyle(n).opacity); }
        return {
            width: r.width, height: r.height, top: r.top,
            fontSize: parseFloat(s.fontSize), fontWeight: parseInt(s.fontWeight, 10),
            color: s.color, opacity: s.opacity, effectiveOpacity: Math.round(effective * 1000) / 1000,
            matches: all.length,
            text: (e.textContent || "").trim().slice(0, 40),
        };
    })()');

    expect($css)->not->toBeNull("element not on the page: {$selector}");

    // The DOM read above takes the first match; screenshotElement() below runs
    // through Playwright, which refuses a selector that matches more than one.
    // If the two ever disagreed, the numbers would describe a different element
    // than the record says — so the selector has to be unambiguous, and this is
    // where that is established rather than assumed.
    expect([$selector, $css['matches']])->toBe([$selector, 1]);

    pixelParkPointer($page);
    $page->screenshotElement($selector, $filename);
    $path = base_path('tests/Browser/Screenshots/'.$filename.'.png');

    $gd = pixelReadPng($path);

    $page->script('(() => { window.__pixelPngBase64 = '.json_encode(base64_encode(file_get_contents($path))).'; return true; })()');
    $page->script(PIXEL_CANVAS_READBACK);
    $page->wait(0.35);
    $canvas = $page->script('window.__pixelRead');

    // The instrument checks itself before it says anything about the page.
    // The ratio is cast because a whole number crosses JSON from JS as an int
    // and comes back from PHP's round() as a float — 21 vs 21.0 is a difference
    // between two languages' number types, not between two decoders.
    expect($canvas)->not->toBeNull()
        ->and($canvas['bg'])->toBe($gd['bg'])
        ->and($canvas['fg'])->toBe($gd['fg'])
        ->and((float) $canvas['ratio'])->toBe($gd['ratio']);

    // Enough glyph pixels that the "furthest colour" is a letter and not a
    // single antialiasing overshoot.
    expect([$selector, $gd['fgCount'] >= 20])->toBe([$selector, true]);

    return ['gd' => $gd, 'canvas' => $canvas, 'css' => $css];
}
