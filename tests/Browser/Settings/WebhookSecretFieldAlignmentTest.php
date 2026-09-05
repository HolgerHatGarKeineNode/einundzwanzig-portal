<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Issue #115 — the secret-retrievability field, measured in pixels
|--------------------------------------------------------------------------
|
| The label "Make secret permanently retrievable" and its description came
| apart: the label wrapped to four lines in a 75.59px column while the
| description started 83.59px further right, so the two read as unrelated
| blocks on the one control that decides whether a secret stays readable.
|
| The cause was placement, not styling. `flux:field variant="inline"` is a
| two-column grid, and its rule for descriptions is a sibling selector —
| `[data-flux-control] ~ [data-flux-description]` pinned to
| `row-start-2 col-start-2`. With the description written after the switch,
| it landed in the switch's `auto` track, that track grew to fit it, and the
| `1fr` label track collapsed to its own min-content. Measured 2026-09-05
| before the fix, at both widths: columns `75.59px 243.41px` (375) and
| `75.59px 428.41px` (1280), label on 4 lines, description offset +83.59px.
|
| Why this test measures geometry and not classes: nothing about the defect
| was expressible as a class. The markup carried no class of ours at all —
| every value above came out of Flux's own utilities, correctly applied to a
| DOM order they were not written for. A class assertion would have passed
| on the broken page, which is exactly why the defect shipped.
|
| What is pinned is the invariant, not the pretty numbers:
|   - the two left edges are identical,
|   - the description sits below the label rather than beside it,
|   - the label track spans the field minus the switch, i.e. it never
|     collapses to min-content again,
|   - the switch stays on row 1, flush right,
|   - nothing overflows,
|   - and the label/description are still wired to the control by id, since
|     the fix is a DOM reorder and that is what a reorder could break.
|
| Line counts are pinned only where there is real headroom. At 1280 the
| English label needs 294px in a 472px track, so one line is safe on any
| font stack. At 375 it needs 294px in a 287px track and legitimately wraps
| once — two lines of a full-width label is ordinary text, not the defect.
|
| Both languages are exercised: German is what the portal renders by default,
| English is the longer string and therefore the harder case.
|
*/

const ISSUE_115_MEASURE = <<<'JS'
(() => {
  const round = (v) => Math.round(v * 100) / 100;
  const rect = (el) => {
    const r = el.getBoundingClientRect();
    return { left: round(r.left), top: round(r.top), right: round(r.right), bottom: round(r.bottom), width: round(r.width), height: round(r.height) };
  };
  const field = document.querySelector('[data-testid="webhook-reveal-secret-field"]');
  if (!field) return null;
  const label = field.querySelector('[data-flux-label]');
  const description = field.querySelector('[data-flux-description]');
  const control = field.querySelector('[data-flux-control]');
  const range = document.createRange();
  range.selectNodeContents(label);
  return {
    text: label.textContent.trim(),
    label: rect(label),
    labelLines: range.getClientRects().length,
    description: rect(description),
    control: rect(control),
    field: rect(field),
    columns: getComputedStyle(field).gridTemplateColumns,
    describedBy: control.getAttribute('aria-describedby'),
    descriptionId: description.id,
    labelledBy: control.getAttribute('aria-labelledby'),
    labelId: label.id,
    fieldScrollWidth: field.scrollWidth,
    fieldClientWidth: field.clientWidth,
    documentScrollWidth: document.documentElement.scrollWidth,
    windowWidth: window.innerWidth,
  };
})()
JS;

/**
 * Resize, then let the layout settle before reading. A measurement taken in
 * the same tick as the resize reads mid-reflow and looks like a regression.
 */
function measureRevealSecretField(object $page, int $width, int $height): array
{
    $page->resize($width, $height);
    $page->wait(1.0);
    $page->script('(() => { void document.documentElement.offsetHeight; return true; })()');
    $page->wait(0.4);

    return $page->script(ISSUE_115_MEASURE);
}

/**
 * The `/en/` in the path is the route's `{country:code}` segment, not the
 * interface language — measured 2026-09-05, `/en/settings/webhooks` renders
 * German. The language comes from `session('lang_country')`, which only the
 * switch route sets.
 */
function visitWebhookSettingsIn(string $langCountry): object
{
    visit('/change_lang_country/'.$langCountry)->wait(1.0);

    return visit('/en/settings/webhooks')->wait(1.2);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('keeps the label and its description on one left edge', function (string $langCountry, string $expectedLabel, int $width, int $height, int $maximumLabelLines) {
    $measured = measureRevealSecretField(visitWebhookSettingsIn($langCountry), $width, $height);

    expect($measured)->not->toBeNull();

    // 0. The language actually switched. Without this the English rows would
    //    silently re-measure the German string, which is 25px shorter and the
    //    easier case — the test would then pass on the harder one untested.
    expect($measured['text'])->toBe($expectedLabel);

    // 1. One unit: same left edge, description underneath rather than beside.
    expect(abs($measured['description']['left'] - $measured['label']['left']))->toBeLessThanOrEqual(0.5)
        ->and($measured['description']['top'])->toBeGreaterThanOrEqual($measured['label']['bottom']);

    // 2. The label track is the field minus the switch and the 8px gap it is
    //    separated by. This is the assertion the defect fails: back then the
    //    track was 75.59px of a 327px field, its own min-content.
    $expectedLabelWidth = $measured['field']['width'] - $measured['control']['width'] - 8;
    expect(abs($measured['label']['width'] - $expectedLabelWidth))->toBeLessThanOrEqual(1.0);

    // 3. The switch stays on row 1, flush with the field's right edge.
    expect(abs($measured['control']['top'] - $measured['label']['top']))->toBeLessThanOrEqual(1.0)
        ->and(abs($measured['control']['right'] - $measured['field']['right']))->toBeLessThanOrEqual(1.0);

    // 4. Wrapping. See the header for why 1280 is pinned hard and 375 is not.
    expect($measured['labelLines'])->toBeLessThanOrEqual($maximumLabelLines);

    // 5. Nothing overflows, at either width.
    expect($measured['fieldScrollWidth'])->toBe($measured['fieldClientWidth'])
        ->and($measured['documentScrollWidth'])->toBeLessThanOrEqual($measured['windowWidth']);

    // 6. The fix is a DOM reorder, so the id wiring is what it could break.
    expect($measured['describedBy'])->toBe($measured['descriptionId'])
        ->and($measured['labelledBy'])->toBe($measured['labelId'])
        ->and($measured['descriptionId'])->not->toBeEmpty();
})->with([
    'German, 375px' => ['de-DE', 'Secret dauerhaft abrufbar machen', 375, 812, 2],
    'German, 1280px' => ['de-DE', 'Secret dauerhaft abrufbar machen', 1280, 900, 1],
    'English, 375px' => ['en-GB', 'Make secret permanently retrievable', 375, 812, 2],
    'English, 1280px' => ['en-GB', 'Make secret permanently retrievable', 1280, 900, 1],
]);

/*
 * Negative control. Putting the description back after the switch on the live
 * DOM has to reproduce the reported failure — otherwise the assertions above
 * are measuring something that was never able to fail.
 */
it('reproduces the split when the description is moved back after the switch', function () {
    $page = visitWebhookSettingsIn('en-GB');

    $healthy = measureRevealSecretField($page, 375, 812);

    $page->script(<<<'JS'
    (() => {
      const field = document.querySelector('[data-testid="webhook-reveal-secret-field"]');
      field.appendChild(field.querySelector('[data-flux-description]'));
      void field.offsetHeight;
      return true;
    })()
    JS);
    $page->wait(0.5);

    $broken = $page->script(ISSUE_115_MEASURE);

    // The very things the test asserts above have to come apart again.
    expect($broken['description']['left'] - $broken['label']['left'])->toBeGreaterThan(50.0)
        ->and($broken['labelLines'])->toBeGreaterThan($healthy['labelLines'])
        ->and($broken['label']['width'])->toBeLessThan($healthy['label']['width'] / 2);
});
