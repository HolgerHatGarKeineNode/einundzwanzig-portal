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

    // Tabbing through the panel's controls must keep focus inside it (Flux's
    // built-in focus trap for a `popover`-backed dropdown), not leak to the page
    // behind it.
    //
    // Getrieben wird ueber das Panel, nicht ueber den Country-Select: seit die
    // drei Selects `variant="listbox"` sind, OEFFNET ein Klick darauf eine eigene
    // Flux-Ebene, und solange die offen ist, fuehrt Tab aus dem Panel heraus —
    // dasselbe Verhalten wie bei country/chooser und timezone/chooser, die dieses
    // Muster seit jeher benutzen. Gemessen: mit geschlossener Listbox bleiben
    // sieben Tabs im Panel, mit geoeffneter verlaesst der fuenfte es.
    $countrySelector = '[data-testid^="calendar-stream-"][data-testid$="-country"]';

    for ($i = 0; $i < 6; $i++) {
        $page->keys($panelSelector, ['Tab']);
        $stillInsidePanel = $page->script(
            "document.activeElement && document.activeElement.closest('[data-testid$=\"-panel\"]') !== null"
        );
        expect($stillInsidePanel)->toBeTrue();
    }

    $page->keys($panelSelector, ['Escape']);

    $activeTestId = $page->script("document.activeElement && document.activeElement.getAttribute('data-testid')");
    expect($activeTestId)->toBe($triggerTestId);
});

/*
 * Regression, gemeldet vom Betreiber mit Screenshot: der Trigger stand als
 * fuenfzeiliger Block in der Werkzeugleiste von /{country}/meetups. Ursache war
 * `!whitespace-normal !h-auto` an einem Button, der in einer `flex ... gap-4`-Zeile
 * ohne `flex-wrap` und ohne `min-w-0` sitzt (meetups/index.blade.php:72): jedes
 * andere Kind dieser Zeile behaelt Flux' `whitespace-nowrap`, also gibt allein der
 * Trigger nach und waechst in die Hoehe statt in die Breite.
 *
 * Der Test misst deshalb die HOEHE, nicht die Breite — und ueber mehrere Breiten,
 * weil die Zeile erst ab `md` nebeneinander laeuft und genau dort eng wird.
 */
it('keeps the calendar trigger on one line at every width', function () {
    $page = visit('/de/meetups');

    foreach ([[1920, 1080], [1440, 900], [1280, 800], [1024, 768], [820, 900], [768, 900]] as [$width, $height]) {
        $page->resize($width, $height);

        $measured = $page->script(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-testid$="-trigger"]');
                return trigger ? Math.round(trigger.getBoundingClientRect().height) : -1;
            })()
        JS);

        // 44px ist das Touch-Target-Minimum (min-h-11), 56px waere bereits die
        // zweite Zeile. Der gemeldete Zustand mass 5 Zeilen.
        expect($measured)->toBeGreaterThan(0, "trigger missing at {$width}px");
        expect($measured)->toBeLessThanOrEqual(56, "trigger wrapped at {$width}px: {$measured}px");
    }
});

/*
 * Issue #55, item 1: 419 timezone identifiers behind a plain <select> inside a
 * 320px popover. The component now uses the repo's own house pattern for these
 * lists (`variant="listbox" searchable` with a search slot — same as
 * livewire/timezone/chooser.blade.php and livewire/country/chooser.blade.php).
 *
 * "Searchable" is a claim about a rendered layer, not about markup: the option
 * list is a separate `popover="manual"` element (flux-pro
 * select/options.blade.php), positioned outside the picker's own panel, so it
 * can sit off-screen while every CSS assertion on the page still passes. This
 * measures it on the narrowest phone width the project cares about.
 */
it('keeps the 419-entry timezone list searchable and inside its container at 375px', function () {
    $page = visit('/de/meetups');

    // resize() returns before the layout has reflowed; measuring straight after
    // it reads mid-reflow geometry (see EventHeaderFitsColumnTest).
    $page->resize(375, 812)->wait(1.2);

    $page->click('[data-testid^="calendar-stream-"][data-testid$="-trigger"]')
        ->assertVisible('[data-testid^="calendar-stream-"][data-testid$="-panel"]')
        ->wait(0.5);

    $page->click('[data-testid^="calendar-stream-"][data-testid$="-timezone"]')->wait(0.8);

    $measured = $page->script(<<<'JS'
        (() => {
            const round = (n) => Math.round(n * 100) / 100;
            const box = (el) => {
                const r = el.getBoundingClientRect();
                return { left: round(r.left), right: round(r.right), top: round(r.top), bottom: round(r.bottom), width: round(r.width), height: round(r.height) };
            };

            const panel = document.querySelector('[data-testid^="calendar-stream-"][data-testid$="-panel"]');
            const select = document.querySelector('[data-testid^="calendar-stream-"][data-testid$="-timezone"]');
            const options = select.querySelector('[data-flux-options]');
            const search = options ? options.querySelector('[data-flux-select-search] input') : null;
            const list = options ? options.querySelector('ui-options') : null;
            const all = list ? Array.from(list.querySelectorAll('ui-option')) : [];
            const visible = all.filter((el) => el.getBoundingClientRect().height > 0);

            const widest = visible.reduce((max, el) => Math.max(max, el.getBoundingClientRect().right), 0);

            return {
                panel: box(panel),
                options: options ? box(options) : null,
                optionsOpen: options ? options.matches(':popover-open') : false,
                search: search ? box(search) : null,
                searchVisible: search ? search.checkVisibility({ checkVisibilityCSS: true }) : false,
                list: list ? box(list) : null,
                listScrollHeight: list ? list.scrollHeight : null,
                optionCountTotal: all.length,
                optionCountVisible: visible.length,
                widestOptionRight: round(widest),
                optionOverflowPastList: list ? round(widest - list.getBoundingClientRect().right) : null,
                viewport: { width: document.documentElement.clientWidth, height: document.documentElement.clientHeight },
                documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            };
        })()
    JS);

    // The 419 identifiers really are all in the DOM — otherwise "searchable"
    // would be measuring an empty list.
    expect($measured['optionCountTotal'])->toBe(count(DateTimeZone::listIdentifiers()));

    // The search field is the whole point of item 1: it has to be on screen.
    expect($measured['searchVisible'])->toBeTrue();
    expect($measured['search']['left'])->toBeGreaterThanOrEqual(0);
    expect($measured['search']['right'])->toBeLessThanOrEqual($measured['viewport']['width']);
    expect($measured['search']['top'])->toBeGreaterThanOrEqual(0);
    expect($measured['search']['bottom'])->toBeLessThanOrEqual($measured['viewport']['height']);

    // The list itself must not run past its own popover, and the popover must
    // not run past the viewport — a floating layer does not grow scrollWidth,
    // so document overflow cannot stand in for this.
    expect($measured['optionOverflowPastList'])->toBeLessThanOrEqual(1);
    expect($measured['options']['left'])->toBeGreaterThanOrEqual(0);
    expect($measured['options']['right'])->toBeLessThanOrEqual($measured['viewport']['width']);
    expect($measured['documentOverflow'])->toBe(0);

    // And it filters: typing has to cut the list down, otherwise the search box
    // is decoration.
    $page->type('[data-testid$="-timezone"] [data-flux-select-search] input', 'Berlin')->wait(0.6);

    $filtered = $page->script(<<<'JS'
        (() => {
            const select = document.querySelector('[data-testid^="calendar-stream-"][data-testid$="-timezone"]');
            const list = select.querySelector('[data-flux-options] ui-options');
            const visible = Array.from(list.querySelectorAll('ui-option'))
                .filter((el) => el.getBoundingClientRect().height > 0);

            return {
                visibleCount: visible.length,
                labels: visible.slice(0, 5).map((el) => el.textContent.trim()),
            };
        })()
    JS);

    expect($filtered['visibleCount'])->toBeGreaterThan(0)
        ->and($filtered['visibleCount'])->toBeLessThan(10)
        ->and($filtered['labels'])->toContain('Europe/Berlin');
});

/*
 * Issue #55, item 4: the component used to build its `data-testid` values from
 * Str::random(8). On /{country}/meetups it sits next to `wire:model.live="search"`
 * (meetups/index.blade.php:76-81), so every keystroke re-rendered the component
 * with fresh ids and every selector-based measurement of it silently addressed a
 * node that no longer existed.
 *
 * Reading the Blade cannot prove the fix — a per-request counter, a component id
 * and a random string all look equally stable in source. This renders the
 * component twice, with a real Livewire round trip in between, and compares.
 */
it('keeps its test ids across a Livewire re-render of the meetups search field', function () {
    Meetup::factory()->create([
        'city_id' => City::query()->firstOrFail()->id,
        'name' => 'Issue 55 Stable Ids Meetup',
        'visible_on_map' => true,
    ]);

    $page = visit('/de/meetups');
    $page->resize(1280, 900)->wait(1.2);

    $collectIds = <<<'JS'
        Array.from(document.querySelectorAll('[data-testid^="calendar-stream-"]'))
            .map((el) => el.getAttribute('data-testid'))
            .sort()
    JS;

    $before = $page->script($collectIds);

    // The component ships six ids per instance (trigger, panel, the three
    // selects, the two copy buttons); asserting on an empty array would pass
    // vacuously.
    expect($before)->not->toBeEmpty();

    $page->type('input[placeholder^="Suche nach Meetups"]', 'Zzz')->wait(1.5);

    // Positive control: the round trip really happened. Without this the
    // comparison below could be comparing a page that never re-rendered.
    $reRendered = $page->script(
        "document.body.innerText.includes('Issue 55 Stable Ids Meetup') === false"
    );
    expect($reRendered)->toBeTrue();

    $after = $page->script($collectIds);

    expect($after)->toBe($before);
});
