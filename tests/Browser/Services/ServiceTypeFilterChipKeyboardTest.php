<?php

use App\Enums\SelfHostedServiceType;

require_once __DIR__.'/../Support/PixelContrast.php';

/*
|--------------------------------------------------------------------------
| Issue #124 — the ten filter chips were divs
|--------------------------------------------------------------------------
|
| `flux:badge` renders `<flux:button-or-div>`, which is a `<div>` unless it is
| given `as="button"`. A div carrying `wire:click` works for a mouse and for
| nothing else: no tab stop, no Enter, no Space, and a screen reader announces
| the label as text rather than as a control with a state.
|
| WHY THIS FILE PRESSES KEYS INSTEAD OF CLICKING. Issue #80's lesson: a
| click-based test passes identically on a div and on a button, which is
| exactly how a keyboard defect survives a green suite.
|
| AND WHY IT USES `keys()` AND NOT `press()`. In pest-plugin-browser,
| `$page->press($button)` is Laravel Dusk's press-a-button: one argument, and
| its whole body is `return $this->click($button);`
| (Api/Concerns/InteractsWithElements.php). Written the obvious way, this file's
| "Space toggles the chip" test was a CLICK test that passed against a div —
| measured while writing it, on the negative control at the bottom. The API that
| dispatches a real key event is `$page->keys($selector, ['Space'])`, which goes
| through Playwright's `locator.press()`: focus the element, then a genuine
| keydown. On a `<div>` the focus is a no-op, the key lands on `<body>`, and
| nothing happens — which is exactly what the negative control now proves.
|
| The chips are toggles, so `aria-pressed`, not `aria-checked` (that is for one
| choice out of a set) and not a link (they filter in place, they do not
| navigate). The row is a `role="group"` with a name, so the ten toggles are
| announced as one control rather than as ten loose words.
|
| The hover and focus renderings are measured here rather than in
| ServiceTypeFilterChipContrastTest because they did not exist before this
| change: Flux' hover fills are written `[&:is(button)]:hover:...` and
| `dark:[button]:hover:...`, both of which compile to `:is(button):hover` and
| therefore matched nothing at all while the chips were divs.
|
*/

/** @return list<string> */
function keyboardChipTypes(): array
{
    return array_map(fn (SelfHostedServiceType $type): string => $type->value, SelfHostedServiceType::cases());
}

/** Reads what a chip is and says about itself. */
function readChipSemantics(object $page, string $type): array
{
    return $page->script('(() => {
        const c = document.querySelector('.json_encode('[data-type="'.$type.'"]').');
        return {
            tag: c.tagName,
            type: c.getAttribute("type"),
            pressed: c.getAttribute("aria-pressed"),
            selected: c.dataset.selected,
            tabIndex: c.tabIndex,
            focused: document.activeElement === c,
        };
    })()');
}

it('makes every chip a real button that reports its own pressed state', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.5);

    foreach (keyboardChipTypes() as $type) {
        $chip = readChipSemantics($page, $type);

        expect([$type, $chip['tag'], $chip['type'], $chip['pressed'], $chip['tabIndex']])
            ->toBe([$type, 'BUTTON', 'button', 'false', 0]);
    }

    // The row names itself, or the ten toggles arrive as ten loose words.
    $group = $page->script('(() => {
        const g = document.querySelector("[data-testid=\'service-type-chip\']").parentElement;
        return { role: g.getAttribute("role"), label: g.getAttribute("aria-label") };
    })()');

    expect($group['role'])->toBe('group')
        ->and($group['label'])->not->toBeEmpty();
});

it('toggles a chip with Space, and reports the change on aria-pressed', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.5);

    $type = keyboardChipTypes()[0];
    $selector = '[data-type="'.$type.'"]';

    // `keys()` focuses the element and then dispatches a real keydown. The mouse
    // is never moved and no click is ever synthesised.
    $page->keys($selector, ['Space'])->wait(1.2);

    $after = readChipSemantics($page, $type);
    expect([$type, $after['pressed'], $after['selected'], $after['focused']])
        ->toBe([$type, 'true', 'true', true]);

    // Space again, off. A toggle that only latches is half a control.
    $page->keys($selector, ['Space'])->wait(1.2);

    $off = readChipSemantics($page, $type);
    expect([$type, $off['pressed'], $off['selected']])->toBe([$type, 'false', 'false']);
});

it('toggles a chip with Enter, and filters the list underneath', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.5);

    $type = keyboardChipTypes()[1];
    $selector = '[data-type="'.$type.'"]';

    $page->keys($selector, ['Enter'])->wait(1.2);

    $state = $page->script('(() => {
        const c = document.querySelector('.json_encode($selector).');
        return {
            pressed: c.getAttribute("aria-pressed"),
            othersPressed: [...document.querySelectorAll("[data-testid=\'service-type-chip\']")].filter((e) => e.getAttribute("aria-pressed") === "true").length,
            resetVisible: !! document.querySelector("[data-testid=\'service-filter-reset\']"),
        };
    })()');

    // The press has to have reached the server, not just the DOM: the reset
    // chip only exists once `$typeFilter` is set on the component.
    expect([$state['pressed'], $state['othersPressed'], $state['resetVisible']])
        ->toBe(['true', 1, true]);
});

it('walks the whole row with Tab, one stop per chip and no chip twice', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.5);

    $types = keyboardChipTypes();
    $landedOn = [];

    // Tab is pressed ON the chip that currently holds focus, which is what
    // `keys()` guarantees by focusing its own selector first. Pressing it on
    // `body` instead would reset focus to the top of the document every time
    // and the walk would be a fiction.
    foreach ($types as $index => $type) {
        $page->keys('[data-type="'.$type.'"]', ['Tab'])->wait(0.25);

        $landedOn[] = $page->script('(() => { const a = document.activeElement; return a ? (a.dataset.type ?? ("<"+a.tagName.toLowerCase()+">")) : null; })()');
    }

    // Tab from chip N lands on chip N+1, for every N. One stop each, DOM order,
    // and no chip reached twice — a chip wrapping a second focusable element
    // would show up as its own successor, which is the "double tab stop" the
    // issue asks about.
    $expected = array_slice($types, 1);
    expect(array_slice($landedOn, 0, count($types) - 1))->toBe($expected);

    // And the row is something you can leave: the last chip hands focus on to
    // something that is not a chip.
    expect(in_array(end($landedOn), $types, true))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The two renderings that only exist now that the chips are buttons
|--------------------------------------------------------------------------
*/

/**
 * @return array{fill: string, ratio: float, ring: float}
 */
function measureChipStateAtPixel(object $page, string $type, string $filename): array
{
    $selector = '[data-type="'.$type.'"]';
    $measured = pixelMeasureText($page, $selector, $filename);

    return [
        'fill' => $measured['gd']['bg'],
        'fg' => $measured['gd']['fg'],
        'ratio' => $measured['gd']['ratio'],
        'width' => $measured['css']['width'],
    ];
}

it('keeps a hovered chip readable in both themes', function (string $theme) {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.5);
    pixelSwitchTheme($page, $theme);

    foreach (keyboardChipTypes() as $type) {
        $selector = '[data-type="'.$type.'"]';

        // At rest first, with the pointer parked, so the hovered reading has
        // something of its own to be compared against.
        $rest = measureChipStateAtPixel($page, $type, "issue-124-{$theme}-rest-{$type}");

        // Now hover it for real and DO NOT park the pointer afterwards. This is
        // the one place in this repository where the trap the contrast test
        // works around is the thing being measured.
        $page->hover($selector)->wait(0.4);
        $page->screenshotElement($selector, "issue-124-{$theme}-hover-{$type}");
        $hovered = pixelReadPng(base_path('tests/Browser/Screenshots/'."issue-124-{$theme}-hover-{$type}.png"));

        // 14px at weight 500 is not WCAG large text, so the hovered rendering
        // owes the same 4.5:1 the resting one does. Left to Flux, seven of the
        // ten did not pay it — see hoverFillOverrides() in the view.
        expect([$theme, $type, 'hover', $hovered['ratio'] >= 4.5])
            ->toBe([$theme, $type, 'hover', true]);

        // The fill is deliberately UNCHANGED, byte for byte. That is the fix:
        // hovering may not repaint a pair that already had to clear 4.5:1.
        expect([$theme, $type, $hovered['bg'], $hovered['fg']])
            ->toBe([$theme, $type, $rest['fill'], $rest['fg']]);

        // Which means the affordance has to come from somewhere else, or there
        // would be no hover feedback at all. It is the 1px inset ring: absent at
        // rest, present on hover, and >= 3:1 against the fill (WCAG 1.4.11).
        $ring = pixelRingBetween(
            base_path('tests/Browser/Screenshots/'."issue-124-{$theme}-rest-{$type}.png"),
            base_path('tests/Browser/Screenshots/'."issue-124-{$theme}-hover-{$type}.png"),
        );

        // On both sides, or what changed was not a ring.
        expect([$theme, $type, 'ring-both-sides', $ring['left'] > 0, $ring['right'] > 0])
            ->toBe([$theme, $type, 'ring-both-sides', true, true]);

        $ringRatio = pixelContrastRatio(
            (int) hexdec(ltrim((string) $ring['ring'], '#')),
            (int) hexdec(ltrim($rest['fill'], '#'))
        );
        expect([$theme, $type, 'ring-3to1', $ringRatio >= 3.0])->toBe([$theme, $type, 'ring-3to1', true]);
    }
})->with(['light', 'dark']);

it('shows a focus indicator on every chip that clears 3:1 against the page', function (string $theme) {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.5);
    pixelSwitchTheme($page, $theme);

    foreach (keyboardChipTypes() as $type) {
        $selector = '[data-type="'.$type.'"]';

        $unfocused = $page->script('(() => {
            const c = document.querySelector('.json_encode($selector).');
            return getComputedStyle(c).outlineStyle;
        })()');

        $page->script('(() => { document.querySelector('.json_encode($selector).').focus(); return true; })()');
        $page->wait(0.3);

        $focused = $page->script('(() => {
            const c = document.querySelector('.json_encode($selector).');
            const s = getComputedStyle(c);
            return { style: s.outlineStyle, width: s.outlineWidth, offset: s.outlineOffset, color: s.outlineColor, textColor: s.color };
        })()');

        // WCAG 2.4.7. `focus-visible` and not `focus`, so a mouse press does
        // not paint a ring nobody asked for — which is why the unfocused read
        // above has to come back as "none": if it did not, the indicator would
        // be permanent rather than a focus indicator.
        expect([$type, $unfocused])->toBe([$type, 'none']);
        expect([$type, $focused['style'], $focused['width'], $focused['offset']])
            ->toBe([$type, 'solid', '2px', '2px']);

        // `outline-current` resolves to the chip's own text colour, which is
        // the whole point: one declaration covers all ten hues, and it inherits
        // a colour that already had to clear 4.5:1 against its own fill.
        expect([$type, $focused['color']])->toBe([$type, $focused['textColor']]);
    }

    // ---- and now at the pixel -------------------------------------------
    // `outline` is painted outside the border box, which `screenshotElement()`
    // clips away, so the ROW is photographed instead: once with nothing
    // focused, then once per chip. The pixels that differ are the indicator,
    // and what they were before is the ground it sits on.
    $row = '[role="group"]';

    $page->script('(() => { document.activeElement?.blur(); return true; })()');
    $page->wait(0.3);
    pixelParkPointer($page);
    $page->screenshotElement($row, "issue-124-{$theme}-row-unfocused");

    $scrollBefore = $page->script('window.scrollY');

    foreach (keyboardChipTypes() as $type) {
        // `preventScroll`, because focusing scrolls the element into view and a
        // scrolled row would differ from the reference in every pixel — the
        // probe would then report an enormous "indicator" made of the whole
        // page moving.
        $page->script('(() => { document.querySelector('.json_encode('[data-type="'.$type.'"]').').focus({ preventScroll: true }); return true; })()');
        $page->wait(0.3);
        pixelParkPointer($page);
        $page->screenshotElement($row, "issue-124-{$theme}-row-focus-{$type}");

        expect([$type, 'no-scroll', $page->script('window.scrollY')])->toBe([$type, 'no-scroll', $scrollBefore]);

        $indicator = pixelDominantChange(
            base_path('tests/Browser/Screenshots/'."issue-124-{$theme}-row-unfocused.png"),
            base_path('tests/Browser/Screenshots/'."issue-124-{$theme}-row-focus-{$type}.png"),
        );

        // Something has to have changed, or the indicator is invisible and
        // 2.4.7 is not met however good the computed style looks.
        expect([$theme, $type, 'indicator-drawn', $indicator['changed'] > 0])
            ->toBe([$theme, $type, 'indicator-drawn', true]);

        $ratio = pixelContrastRatio(
            (int) hexdec(ltrim((string) $indicator['after'], '#')),
            (int) hexdec(ltrim((string) $indicator['before'], '#'))
        );

        // WCAG 1.4.11: a focus indicator is a graphical object, 3:1 against
        // what is adjacent to it.
        expect([$theme, $type, 'indicator-3to1', $ratio >= 3.0])
            ->toBe([$theme, $type, 'indicator-3to1', true]);
    }
})->with(['light', 'dark']);

/*
|--------------------------------------------------------------------------
| Negative controls — put the divs back
|--------------------------------------------------------------------------
*/

it('reproduces the defect when the chips are turned back into divs', function () {
    $page = visit('/de/services');
    $page->resize(1280, 900)->wait(1.5);

    // Same element, same classes, same wire:click — only the tag name differs,
    // which is the entire change issue #124 asked for.
    $swapped = $page->script(<<<'JS'
    (() => {
      let n = 0;
      document.querySelectorAll('[data-testid="service-type-chip"]').forEach((chip) => {
        const div = document.createElement('div');
        for (const a of chip.attributes) { div.setAttribute(a.name, a.value); }
        div.removeAttribute('type');
        div.removeAttribute('aria-pressed');
        div.innerHTML = chip.innerHTML;
        chip.replaceWith(div);
        n++;
      });
      return n;
    })()
    JS);
    $page->wait(0.4);

    expect($swapped)->toBe(count(keyboardChipTypes()));

    $type = keyboardChipTypes()[0];
    $before = readChipSemantics($page, $type);

    // A div is not in the tab order and has no pressed state to report.
    expect([$before['tag'], $before['pressed'], $before['tabIndex']])
        ->toBe(['DIV', null, -1]);

    // And Space does nothing to it. This is the assertion the whole file
    // exists for: run it against the shipped page and it fails, which is what
    // makes the passing version above mean something.
    $page->keys('[data-type="'.$type.'"]', ['Space'])->wait(1.2);

    $after = readChipSemantics($page, $type);
    expect([$type, $after['selected'], $after['focused']])->toBe([$type, 'false', false]);
});
