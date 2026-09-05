<?php

use App\Enums\SelfHostedServiceType;

/*
|--------------------------------------------------------------------------
| Issue #114 — the ten service-type filter chips, measured at the pixel
|--------------------------------------------------------------------------
|
| `flux:badge size="lg"` sets 14px at weight 500. That is not WCAG "large
| text" (1.4.3 draws the line at 18pt, or 14pt bold), so every chip has to
| reach 4.5:1 — in both themes, selected and unselected.
|
| What it used to do: `opacity-70` on the unselected chips. Opacity fades the
| glyph and the fill by the same factor, so the ratio between them collapses
| with it. Measured on this repository before the fix, pointer parked off the
| row: light 2.756–4.050:1, dark 3.505–4.093:1 — all ten below the threshold
| in both themes. The negative control at the bottom of this file puts the
| class back and has to reproduce that.
|
| Two separate defects were fixed, and both are pinned here:
|   1. the `opacity-70`, replaced by a state indicator that is an EDGE and a
|      GLYPH rather than a second colour;
|   2. Flux' own light-page text token for amber (#BB4D00 on #FFEEBF, 4.368:1)
|      and orange (#CA3500 on #FFE7CD, 4.369:1), which misses 4.5:1 on its own
|      — the same amber issue #98 took off the webhook badge. The view now
|      overrides those two to the -800 shade.
|
| Why this measures instead of computing: the badge fill is an alpha colour
| (`bg-<hue>-400/20`), so the pair that reaches the eye exists only after the
| compositor has laid it over whatever ground the page provides. Computing it
| from the Tailwind tokens was measured 2026-09-05 to be ~9% off in dark mode.
| Two independent decoders read the same PNG — PHP's GD (`imagecolorat`) and a
| canvas `getImageData()` readback inside the page — and they have to agree,
| or the instrument, not the page, is what is being measured.
|
| Measuring trap this file has to work around: `screenshotElement()` does not
| move the mouse, so a chip left under the cursor by an earlier click renders
| its :hover state. Measured while writing this: zinc came back 5.827:1 where
| 4.093:1 was correct. `parkChipPointer()` is not decoration.
|
| The selected/unselected difference is deliberately NOT a colour difference:
| both states carry the identical fill and glyph colour (see the assertions —
| they are compared byte for byte). The state is carried by a 2px inset ring
| in the chip's own text colour plus a check glyph. That is why this file
| measures the RING against the FILL rather than running a ΔE00 between two
| fills the way the #98 test does: there is no hue comparison left to fail,
| for a dichromat or for anyone else. The ring probe reads 1.000:1 on an
| unselected chip and >= 3:1 on a selected one, which is its own positive and
| negative control in one number.
|
*/

/** WCAG 2.1 relative luminance of a packed 0xRRGGBB value. */
function chipRelativeLuminance(int $rgb): float
{
    $channel = function (float $c): float {
        $c /= 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * $channel(($rgb >> 16) & 255)
        + 0.7152 * $channel(($rgb >> 8) & 255)
        + 0.0722 * $channel($rgb & 255);
}

function chipContrastRatio(int $first, int $second): float
{
    $lighter = max(chipRelativeLuminance($first), chipRelativeLuminance($second));
    $darker = min(chipRelativeLuminance($first), chipRelativeLuminance($second));

    return round(($lighter + 0.05) / ($darker + 0.05), 3);
}

/**
 * Viénot, Brettel & Mollon (1999) dichromat reduction, applied to linear RGB.
 * Returns the colour a protanope or deuteranope sees, so the same luminance
 * arithmetic can be run on it.
 */
function chipDichromatReduce(int $rgb, string $vision): int
{
    if ($vision === 'normal') {
        return $rgb;
    }

    $linear = fn (float $c): float => ($c /= 255) <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    $encode = function (float $c): int {
        $c = max(0.0, min(1.0, $c));

        return (int) round(255 * ($c <= 0.0031308 ? 12.92 * $c : 1.055 * $c ** (1 / 2.4) - 0.055));
    };

    $r = $linear(($rgb >> 16) & 255);
    $g = $linear(($rgb >> 8) & 255);
    $b = $linear($rgb & 255);

    $matrix = $vision === 'protan'
        ? [[0.11238, 0.88762, 0.0], [0.11238, 0.88762, 0.0], [0.00401, -0.00401, 1.0]]
        : [[0.29275, 0.70725, 0.0], [0.29275, 0.70725, 0.0], [-0.02234, 0.02234, 1.0]];

    [$rr, $gg, $bb] = array_map(
        fn (array $row): int => $encode($row[0] * $r + $row[1] * $g + $row[2] * $b),
        $matrix
    );

    return ($rr << 16) | ($gg << 8) | $bb;
}

/**
 * Most frequent colour in the PNG is the fill; the one furthest from it in
 * relative luminance is the glyph core (antialiasing only ever produces
 * colours BETWEEN the two, so this cannot overstate the contrast).
 *
 * `edge` samples one CSS pixel in from the left and the right border at half
 * height — inside a 2px inset ring, outside nothing else. Both samples have to
 * agree, which is the probe checking itself before it says anything about the
 * page. `$cssWidth` is what turns CSS pixels into device pixels; it is read off
 * the live element rather than assumed, so this survives any devicePixelRatio.
 *
 * @return array{bg: string, fg: string, fgCount: int, ratio: float, edge: string, edgeRatio: float, width: int, height: int, scale: float}
 */
function measureFilterChipFromPng(string $path, float $cssWidth): array
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
    arsort($histogram);

    $background = array_key_first($histogram);
    $backgroundLuminance = chipRelativeLuminance($background);

    $foreground = $background;
    $largestDistance = 0.0;
    foreach ($histogram as $key => $count) {
        $distance = abs(chipRelativeLuminance($key) - $backgroundLuminance);
        if ($distance > $largestDistance) {
            $largestDistance = $distance;
            $foreground = $key;
        }
    }

    $scale = $width / $cssWidth;
    $inset = (int) round($scale);
    $middle = intdiv($height, 2);
    $leftEdge = imagecolorat($image, $inset, $middle) & 0xFFFFFF;
    $rightEdge = imagecolorat($image, $width - 1 - $inset, $middle) & 0xFFFFFF;
    imagedestroy($image);

    return [
        'bg' => sprintf('#%06X', $background),
        'fg' => sprintf('#%06X', $foreground),
        'fgCount' => $histogram[$foreground],
        'ratio' => chipContrastRatio($foreground, $background),
        'edge' => sprintf('#%06X', $leftEdge),
        'edgeMirror' => sprintf('#%06X', $rightEdge),
        'edgeRatio' => chipContrastRatio($leftEdge, $background),
        'width' => $width,
        'height' => $height,
        'scale' => round($scale, 3),
    ];
}

/** The same extraction rule, run by Chromium's PNG decoder instead of GD. */
const CHIP_CANVAS_READBACK = <<<'JS'
(() => {
  const image = new Image();
  window.__chipRead = null;
  image.onload = () => {
    const canvas = document.createElement('canvas');
    canvas.width = image.width;
    canvas.height = image.height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(image, 0, 0);
    const data = context.getImageData(0, 0, canvas.width, canvas.height).data;
    const at = (x, y) => { const i = (y * canvas.width + x) * 4; return (data[i] << 16) | (data[i + 1] << 8) | data[i + 2]; };

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

    const scale = canvas.width / window.__chipCssWidth;
    const inset = Math.round(scale);
    const middle = Math.floor(canvas.height / 2);
    const leftEdge = at(inset, middle);
    const rightEdge = at(canvas.width - 1 - inset, middle);

    const hex = (k) => '#' + k.toString(16).toUpperCase().padStart(6, '0');
    window.__chipRead = {
      bg: hex(background),
      fg: hex(foreground),
      fgCount: histogram.get(foreground),
      ratio: ratio(foreground, background),
      edge: hex(leftEdge),
      edgeMirror: hex(rightEdge),
      edgeRatio: ratio(leftEdge, background),
      width: canvas.width,
      height: canvas.height,
      scale: Math.round(scale * 1000) / 1000,
    };
  };
  image.src = 'data:image/png;base64,' + window.__pngBase64;
  return true;
})()
JS;

/**
 * Moves the real pointer off the chip row. `screenshotElement()` leaves the
 * mouse where the last click put it, and a hovered chip is a different
 * rendering — measured: 5.827:1 instead of 4.093:1.
 */
function parkChipPointer(object $page): void
{
    $page->hover('[data-flux-heading].text-2xl')->wait(0.2);
}

function switchChipAppearance(object $page, string $theme): void
{
    $page->script('(() => { document.documentElement.classList.'.($theme === 'dark' ? 'add' : 'remove').'("dark"); void document.documentElement.offsetHeight; return true; })()');
    $page->wait(0.4);
}

/**
 * Screenshots one chip, then reads the same file back twice.
 *
 * @return array{gd: array<string, mixed>, canvas: array<string, mixed>, css: array<string, mixed>}
 */
function readFilterChipTwice(object $page, string $type, string $filename): array
{
    $selector = '[data-type="'.$type.'"]';

    $css = $page->script('(() => { const c = document.querySelector('.json_encode($selector).'); const s = getComputedStyle(c); const r = c.getBoundingClientRect(); return { width: r.width, height: r.height, right: r.right, fontSize: s.fontSize, fontWeight: s.fontWeight, opacity: s.opacity, selected: c.dataset.selected, hasIcon: !!c.querySelector("[data-flux-badge-icon]") }; })()');

    parkChipPointer($page);
    $page->screenshotElement($selector, $filename);
    $path = base_path('tests/Browser/Screenshots/'.$filename.'.png');

    $gd = measureFilterChipFromPng($path, (float) $css['width']);

    $page->script('(() => { window.__pngBase64 = '.json_encode(base64_encode(file_get_contents($path))).'; window.__chipCssWidth = '.json_encode($css['width']).'; return true; })()');
    $page->script(CHIP_CANVAS_READBACK);
    $page->wait(0.4);

    return ['gd' => $gd, 'canvas' => $page->script('window.__chipRead'), 'css' => $css];
}

/** @return list<string> */
function serviceTypeValues(): array
{
    return array_map(fn (SelfHostedServiceType $type): string => $type->value, SelfHostedServiceType::cases());
}

/**
 * Every chip of one theme, both states, measured and asserted.
 *
 * Unselected first (nothing is selected on arrival), then one chip at a time
 * is clicked, read back and clicked off again.
 */
function assertEveryChipIsReadable(object $page, string $theme): array
{
    $measured = [];

    foreach (serviceTypeValues() as $type) {
        $unselected = readFilterChipTwice($page, $type, 'issue-114-'.$theme.'-unselected-'.$type);

        // Both decoders, or the instrument is what is being measured.
        expect($unselected['canvas'])->not->toBeNull()
            ->and($unselected['canvas']['bg'])->toBe($unselected['gd']['bg'])
            ->and($unselected['canvas']['fg'])->toBe($unselected['gd']['fg'])
            ->and($unselected['canvas']['ratio'])->toBe($unselected['gd']['ratio'])
            ->and($unselected['canvas']['edge'])->toBe($unselected['gd']['edge']);

        // 14px at weight 500 is not WCAG large text, which is why the floor
        // below is 4.5 and not 3.0. And nothing may fade the pair again.
        expect([$type, $unselected['css']['fontSize'], $unselected['css']['fontWeight'], $unselected['css']['opacity']])
            ->toBe([$type, '14px', '500', '1']);

        expect($unselected['gd']['fgCount'])->toBeGreaterThan(100);
        expect([$theme, $type, 'unselected', $unselected['gd']['ratio'] >= 4.5])
            ->toBe([$theme, $type, 'unselected', true]);

        // No ring on an unselected chip: the edge probe reads the fill itself.
        expect([$type, $unselected['gd']['edge'], $unselected['gd']['edgeMirror']])
            ->toBe([$type, $unselected['gd']['bg'], $unselected['gd']['bg']]);
        expect($unselected['css']['hasIcon'])->toBeFalse();

        $page->click('[data-type="'.$type.'"]')->wait(0.8);
        $selected = readFilterChipTwice($page, $type, 'issue-114-'.$theme.'-selected-'.$type);

        expect($selected['canvas'])->not->toBeNull()
            ->and($selected['canvas']['bg'])->toBe($selected['gd']['bg'])
            ->and($selected['canvas']['fg'])->toBe($selected['gd']['fg'])
            ->and($selected['canvas']['ratio'])->toBe($selected['gd']['ratio'])
            ->and($selected['canvas']['edge'])->toBe($selected['gd']['edge']);

        expect([$theme, $type, 'selected', $selected['gd']['ratio'] >= 4.5])
            ->toBe([$theme, $type, 'selected', true]);

        // Selecting a chip must not repaint it. The state is an edge and a
        // glyph, so fill and text come back byte-identical — which is also why
        // no dichromat can lose the distinction to a hue collision.
        expect([$type, $selected['gd']['bg'], $selected['gd']['fg']])
            ->toBe([$type, $unselected['gd']['bg'], $unselected['gd']['fg']]);

        // Same probe, same place: on the selected chip it now sits in the ring.
        expect([$type, $selected['gd']['edge']])->toBe([$type, $selected['gd']['edgeMirror']]);
        expect([$type, $selected['gd']['edge'] === $selected['gd']['bg']])->toBe([$type, false]);

        // WCAG 1.4.11: a state indicator is a graphical object, 3:1. Checked
        // for normal vision and through both dichromat reductions, because a
        // reduction moves luminance as well as hue.
        $ring = (int) hexdec(ltrim($selected['gd']['edge'], '#'));
        $fill = (int) hexdec(ltrim($selected['gd']['bg'], '#'));
        $ringRatios = [];
        foreach (['normal', 'protan', 'deutan'] as $vision) {
            $ringRatios[$vision] = chipContrastRatio(
                chipDichromatReduce($ring, $vision),
                chipDichromatReduce($fill, $vision)
            );
            expect([$theme, $type, $vision, $ringRatios[$vision] >= 3.0])
                ->toBe([$theme, $type, $vision, true]);
        }

        // The check glyph, so the state is not carried by the edge alone
        // (WCAG 1.4.1), and it widens the chip measurably.
        expect($selected['css']['hasIcon'])->toBeTrue();
        expect([$type, $selected['css']['width'] - $unselected['css']['width'] > 15])
            ->toBe([$type, true]);

        $measured[$type] = [
            'bg' => $selected['gd']['bg'],
            'fg' => $selected['gd']['fg'],
            'text' => $selected['gd']['ratio'],
            'ring' => $ringRatios,
            'widthDelta' => round($selected['css']['width'] - $unselected['css']['width'], 2),
        ];

        $page->click('[data-type="'.$type.'"]')->wait(0.8);
    }

    return $measured;
}

it('keeps all ten type chips above 4.5:1 on the light page, selected and not', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.2);
    switchChipAppearance($page, 'light');

    $measured = assertEveryChipIsReadable($page, 'light');

    expect($measured)->toHaveCount(10);

    // Flat alpha fills, byte-exact, so these are pinned: a silent retune of a
    // Flux token or of the two -800 overrides changes them.
    expect($measured['mempool']['bg'])->toBe('#DCECFF')
        ->and($measured['mempool']['fg'])->toBe('#193CB8');

    // The two the view overrides. With Flux' own -700 they measured 4.368 and
    // 4.369 here; -800 is what carries them over the line.
    expect($measured['alby']['fg'])->toBe('#973C00')
        ->and($measured['pkarr_dns_server']['fg'])->toBe('#9F2D00');

    // Weakest of the ten measured 4.819 (pink); 4.6 is the floor with headroom
    // for font rendering and still above anything opacity-70 could reach.
    $weakest = min(array_column($measured, 'text'));
    expect($weakest)->toBeGreaterThan(4.6);
});

it('keeps all ten type chips above 4.5:1 on the dark page, selected and not', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.2);
    switchChipAppearance($page, 'dark');

    $measured = assertEveryChipIsReadable($page, 'dark');

    expect($measured)->toHaveCount(10);

    // blue-400/40 over the dark page, which in this build is zinc-800.
    expect($measured['mempool']['bg'])->toBe('#37587F')
        ->and($measured['mempool']['fg'])->toBe('#BEDBFF');

    // Weakest of the ten measured 4.694 (amber). Dark mode never needed the
    // override, and it keeps Flux' own -200 text token.
    expect($measured['alby']['fg'])->toBe('#FEE685');

    $weakest = min(array_column($measured, 'text'));
    expect($weakest)->toBeGreaterThan(4.6);
});

/*
 * Negative control. Putting `opacity-70` back on the live DOM has to reproduce
 * the reported failure on every one of the ten — otherwise the two tests above
 * are measuring something that was never able to fail.
 */
it('reproduces the failure on all ten when opacity-70 is put back', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.2);
    switchChipAppearance($page, 'light');

    $page->script(<<<'JS'
    (() => {
      document.querySelectorAll('[data-testid="service-type-chip"]').forEach((chip) => chip.classList.add('opacity-70'));
      void document.body.offsetHeight;
      return true;
    })()
    JS);
    $page->wait(0.4);

    $ratios = [];
    foreach (serviceTypeValues() as $type) {
        $measured = readFilterChipTwice($page, $type, 'issue-114-control-opacity-'.$type);

        expect($measured['css']['opacity'])->toBe('0.7');
        expect($measured['canvas']['ratio'])->toBe($measured['gd']['ratio']);

        $ratios[$type] = $measured['gd']['ratio'];
        expect([$type, $measured['gd']['ratio'] < 4.5])->toBe([$type, true]);
    }

    // The band issue #114 reported for the light page (2.717–4.029 there, read
    // on a different font rendering); nothing here may reach the threshold.
    expect(max($ratios))->toBeLessThan(4.5)
        ->and(min($ratios))->toBeLessThan(3.5);
});

/*
 * The row wraps rather than scrolls. Narrow first: 375px is where a chip cloud
 * of ten labels either wraps or pushes a horizontal scrollbar onto the whole
 * document. One visit per viewport — reading geometry straight after a resize
 * catches the page mid-reflow in this repository.
 */
it('wraps the chip row without overflowing at 375px', function () {
    $page = visit('/de/services');
    $page->resize(375, 800)->wait(1.5);

    $geometry = $page->script('(() => { void document.documentElement.offsetHeight; const row = document.querySelector("[data-testid=\'service-type-chip\']").parentElement; const r = row.getBoundingClientRect(); const chips = [...document.querySelectorAll("[data-testid=\'service-type-chip\']")]; return { viewport: window.innerWidth, docScroll: document.documentElement.scrollWidth, docClient: document.documentElement.clientWidth, rowScroll: row.scrollWidth, rowClient: row.clientWidth, rowHeight: Math.round(r.height), widest: Math.max(...chips.map((c) => Math.ceil(c.getBoundingClientRect().right))), rows: new Set(chips.map((c) => Math.round(c.getBoundingClientRect().top))).size, minHeight: Math.min(...chips.map((c) => c.getBoundingClientRect().height)) }; })()');

    expect($geometry['docScroll'])->toBeLessThanOrEqual($geometry['docClient'])
        ->and($geometry['rowScroll'])->toBeLessThanOrEqual($geometry['rowClient'])
        ->and($geometry['widest'])->toBeLessThanOrEqual($geometry['viewport']);

    // It has to wrap at this width, or the assertion above would be passing
    // for the wrong reason (a row that happens to fit proves nothing).
    expect($geometry['rows'])->toBeGreaterThan(1);

    // WCAG 2.5.8 target size, 24px minimum.
    expect($geometry['minHeight'])->toBeGreaterThanOrEqual(24.0);
});

it('does not overflow at 1280px, with a chip selected', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.5);

    $page->click('[data-type="electrum_fulcrum_server"]')->wait(1.0);

    $geometry = $page->script('(() => { void document.documentElement.offsetHeight; const row = document.querySelector("[data-testid=\'service-type-chip\']").parentElement; const chips = [...document.querySelectorAll("[data-testid=\'service-type-chip\']")]; return { viewport: window.innerWidth, docScroll: document.documentElement.scrollWidth, docClient: document.documentElement.clientWidth, rowScroll: row.scrollWidth, rowClient: row.clientWidth, widest: Math.max(...chips.map((c) => Math.ceil(c.getBoundingClientRect().right))), rows: new Set(chips.map((c) => Math.round(c.getBoundingClientRect().top))).size, selected: document.querySelectorAll("[data-selected=\'true\']").length }; })()');

    expect($geometry['selected'])->toBe(1)
        ->and($geometry['docScroll'])->toBeLessThanOrEqual($geometry['docClient'])
        ->and($geometry['rowScroll'])->toBeLessThanOrEqual($geometry['rowClient'])
        ->and($geometry['widest'])->toBeLessThanOrEqual($geometry['viewport']);
});
