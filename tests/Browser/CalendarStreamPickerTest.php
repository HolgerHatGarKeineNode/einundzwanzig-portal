<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;

/*
 * Issue e5233554: the country/language/timezone dropdown next to the calendar-stream
 * copy buttons is a first-class requirement, not decoration — 360px width, keyboard
 * operation and focus handling have to be checked on a real rendered page, not assumed
 * from the markup. This covers the `/de/meetups` instance of `<x-calendar-stream-picker>`;
 * the per-meetup instances share the same component and Alpine logic.
 */
beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    Meetup::factory()->create(['city_id' => $city->id, 'visible_on_map' => true]);
});

it('is usable at 360px width with visible labels and 44px touch targets, in light and dark mode', function () {
    $page = visit('/de/meetups');

    $page->resize(360, 740)
        ->assertNoJavaScriptErrors()
        ->click('[data-testid^="calendar-stream-"][data-testid$="-trigger"]')
        ->assertVisible('[data-testid^="calendar-stream-"][data-testid$="-panel"]')
        ->assertVisible('[data-testid^="calendar-stream-"][data-testid$="-country"]')
        ->assertVisible('[data-testid^="calendar-stream-"][data-testid$="-language"]')
        ->assertVisible('[data-testid^="calendar-stream-"][data-testid$="-timezone"]')
        ->assertSee('Land')
        ->assertSee('Sprache')
        ->assertSee('Zeitzone');

    $hasHorizontalOverflow = $page->script('document.documentElement.scrollWidth > document.documentElement.clientWidth + 1');
    expect($hasHorizontalOverflow)->toBeFalse();

    // Issue febad80c: the "Nur Meetup Events des gewählten Landes kopieren" button's
    // text forced Flux's default `whitespace-nowrap` single-line width past the
    // popover's edge. A floating popover panel sits outside normal document flow, so
    // that overflow never grew document.documentElement.scrollWidth above — the check
    // needs to compare each button's own bounding box against its panel's.
    $copyButtonOverflowsPanel = $page->script(<<<'JS'
        (() => {
            const panel = document.querySelector('[data-testid^="calendar-stream-"][data-testid$="-panel"]');
            const panelRight = panel.getBoundingClientRect().right;
            return Array.from(panel.querySelectorAll(
                '[data-testid$="-copy-all"], [data-testid$="-copy-scoped"]'
            )).some((el) => el.getBoundingClientRect().right > panelRight + 1);
        })()
    JS);
    expect($copyButtonOverflowsPanel)->toBeFalse();

    $touchTargetHeights = $page->script(<<<'JS'
        Array.from(document.querySelectorAll(
            '[data-testid^="calendar-stream-"][data-testid$="-country"],'
            + '[data-testid^="calendar-stream-"][data-testid$="-language"],'
            + '[data-testid^="calendar-stream-"][data-testid$="-timezone"],'
            + '[data-testid^="calendar-stream-"][data-testid$="-copy-all"],'
            + '[data-testid^="calendar-stream-"][data-testid$="-copy-scoped"]'
        )).map((el) => el.getBoundingClientRect().height)
    JS);

    foreach ($touchTargetHeights as $height) {
        expect($height)->toBeGreaterThanOrEqual(44);
    }

    // Default appearance (see header.blade.php: dark unless the visitor picked light).
    expect($page->script("document.documentElement.classList.contains('dark')"))->toBeTrue();
    $page->assertNoJavaScriptErrors();

    // Force light mode and confirm the same panel still renders without errors.
    $page->script("localStorage.setItem('flux.appearance', 'light')");
    $page = $page->navigate($page->url());
    $page->resize(360, 740)
        ->assertNoJavaScriptErrors();
    expect($page->script("document.documentElement.classList.contains('dark')"))->toBeFalse();
    $page->click('[data-testid^="calendar-stream-"][data-testid$="-trigger"]')
        ->assertVisible('[data-testid^="calendar-stream-"][data-testid$="-panel"]')
        ->assertSee('Land')
        ->assertSee('Sprache')
        ->assertSee('Zeitzone');
});

it('is keyboard-operable, traps focus in the open panel and returns it to the trigger on close', function () {
    $page = visit('/de/meetups');

    $triggerSelector = '[data-testid^="calendar-stream-"][data-testid$="-trigger"]';
    $panelSelector = '[data-testid^="calendar-stream-"][data-testid$="-panel"]';
    $triggerTestId = $page->attribute($triggerSelector, 'data-testid');

    $page->click($triggerSelector)
        ->assertVisible($panelSelector);

    // Country select is the first control inside the panel; tabbing through all
    // five focusable controls must keep focus inside the panel (Flux's built-in
    // focus trap for a `popover`-backed dropdown), not leak to the page behind it.
    $countrySelector = '[data-testid^="calendar-stream-"][data-testid$="-country"]';
    $page->click($countrySelector);

    for ($i = 0; $i < 6; $i++) {
        $page->keys($countrySelector, ['Tab']);
        $stillInsidePanel = $page->script(
            "document.activeElement && document.activeElement.closest('[data-testid$=\"-panel\"]') !== null"
        );
        expect($stillInsidePanel)->toBeTrue();
    }

    $page->keys($countrySelector, ['Escape']);

    $activeTestId = $page->script("document.activeElement && document.activeElement.getAttribute('data-testid')");
    expect($activeTestId)->toBe($triggerTestId);
});
