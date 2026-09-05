<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;

/*
 * Issue #66: the right-hand column (`md:w-1/3`) of the event page was narrower
 * than its own contents. Measured at a 1280px viewport before the fix: the
 * column ended at x=1232, its contents ran to x=1290.95 — 58.95px past the
 * column and 10.95px past the viewport — while `documentElement.scrollWidth`
 * stayed at 1280. No scrollbar, so the meetup name, the city line and the
 * "Kalender abonnieren" button were silently cut off. Content loss, not a
 * tight fit, which is why a CSS-class assertion cannot stand in for this test:
 * only real geometry catches it.
 */
beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Frankfurt am Main',
    ]);
    // The long meetup name and the mapped venue are the load case, not
    // decoration: the name drives the header's max-content width, and the
    // venue map is the other block sharing the column.
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Bitcoin Meetup Frankfurt am Main 21',
        'slug' => 'issue-66-frankfurt',
        'visible_on_map' => true,
    ]);
    $this->event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'location' => 'Hauptbahnhof 1, 60329 Frankfurt am Main',
        'osm_name' => 'Frankfurt Hauptbahnhof',
        'osm_lat' => 50.107,
        'osm_lon' => 8.6636,
    ]);

    $this->eventUrl = route('meetups.landingpage-event', [
        'country' => 'de',
        'meetup' => $this->meetup->slug,
        'event' => $this->event->id,
    ], absolute: false);

    /*
     * Clipped geometry, deliberately: Leaflet lays its tile images out beyond
     * the map pane and hides the surplus with `overflow: hidden`. Their raw
     * rects sit up to 326px past the column (measured at 1280) and would fail
     * every assertion here on invisible boxes. So each rect is clipped against
     * every ancestor whose `overflow-x` is not `visible` before it is compared
     * — the same thing the eye does. The column itself is never a clipper here
     * (it has no overflow of its own), which the negative control below
     * verifies by making the probe report the defect again.
     */
    $this->probe = <<<'JS'
        (() => {
          const col = Array.from(document.querySelectorAll('div')).find(
            (d) => typeof d.className === 'string' && d.className.split(/\s+/).includes('md:w-1/3')
          );
          if (! col) { return {error: 'right column not found'}; }
          const c = col.getBoundingClientRect();
          const clippedRight = (el) => {
            let right = el.getBoundingClientRect().right;
            let p = el.parentElement;
            while (p && p !== col.parentElement) {
              if (getComputedStyle(p).overflowX !== 'visible') {
                right = Math.min(right, p.getBoundingClientRect().right);
              }
              p = p.parentElement;
            }
            return right;
          };
          const items = [];
          for (const child of col.querySelectorAll('*')) {
            const r = child.getBoundingClientRect();
            if (r.width === 0 && r.height === 0) { continue; }
            items.push({
              tag: child.tagName,
              right: Math.round(clippedRight(child) * 100) / 100,
              text: (child.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 30),
            });
          }
          const maxRight = Math.max(...items.map((i) => i.right));
          const trigger = col.querySelector('[data-testid$="-trigger"]');
          const tr = trigger.getBoundingClientRect();
          return {
            viewport: window.innerWidth,
            colWidth: Math.round(c.width * 100) / 100,
            colRight: Math.round(c.right * 100) / 100,
            maxRight: maxRight,
            overhang: Math.round((maxRight - c.right) * 100) / 100,
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
            trigger: {
              width: Math.round(tr.width * 100) / 100,
              height: Math.round(tr.height * 100) / 100,
              // A wrapped label that no longer fits its own button would be cut
              // just as silently as the button was cut from the column.
              clipped: trigger.scrollWidth > trigger.clientWidth + 1
                || trigger.scrollHeight > trigger.clientHeight + 1,
            },
            worst: items.filter((i) => i.right > c.right + 0.5)
              .sort((a, b) => b.right - a.right).slice(0, 3),
          };
        })()
    JS;

    /*
     * `resize()` returns before the layout has reflowed — measuring straight
     * after it reads the previous viewport (known trap in this repo). Read a
     * layout property to force the reflow, then measure.
     */
    $this->measureAt = function ($page, int $width, int $height) {
        $page->resize($width, $height)->wait(1.2);
        $page->script('document.documentElement.getBoundingClientRect().width');
        $page->wait(0.5);

        return $page->script($this->probe);
    };
});

it('keeps the right column contents inside the column from 1440 down to 375', function () {
    $page = visit($this->eventUrl);

    // 1280 is the width the issue was filed at; 1440 overflowed by 5.63px per
    // its own sweep; 1024 is the narrowest the column ever gets while the
    // two-column layout is on (202.67px measured), so it is the worst case for
    // the calendar button; 375 is the narrow end.
    foreach ([[1440, 900], [1280, 900], [1024, 900], [375, 812]] as [$width, $height]) {
        $m = ($this->measureAt)($page, $width, $height);

        expect($m['error'] ?? null)->toBeNull();

        // The defect itself: contents ending past the column's right edge.
        expect($m['overhang'])->toBeLessThanOrEqual(0.5, sprintf(
            'viewport %s: contents end at x=%s, %spx past the column edge at x=%s — %s',
            $m['viewport'], $m['maxRight'], $m['overhang'], $m['colRight'], json_encode($m['worst'])
        ));

        // ... and its nastiest property: it produced no scrollbar, so nothing
        // on screen announced that anything was missing.
        expect($m['scrollWidth'])->toBeLessThanOrEqual(
            $m['clientWidth'],
            sprintf('viewport %s: document scrolls horizontally', $m['viewport'])
        );

        // The calendar trigger may wrap its label, not swallow it, and it keeps
        // the 44px touch target while wrapping (WCAG 2.5.8, Apple HIG).
        expect($m['trigger']['clipped'])->toBeFalse(
            sprintf('viewport %s: the calendar button clips its own label', $m['viewport'])
        );
        expect($m['trigger']['height'])->toBeGreaterThanOrEqual(
            44,
            sprintf('viewport %s: calendar button is %spx high', $m['viewport'], $m['trigger']['height'])
        );
    }

    $page->assertNoJavaScriptErrors();
});

/*
 * The name is the other content that can outgrow the column, and it is the
 * worse case: a one-word name is one unbreakable word. Measured at 1280 with
 * `wrap-anywhere` removed, the header ran 262.95px past the column — and
 * 266.61px at 375, the mobile width the reported defect spared. `break-words`
 * would not help: `overflow-wrap: break-word` does not lower an element's
 * min-content width, `overflow-wrap: anywhere` does.
 */
it('keeps a single-word meetup name inside the column', function () {
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Bitcoinmeetupfrankfurtammainundumgebung',
        'slug' => 'issue-66-longword',
        'visible_on_map' => true,
    ]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'location' => 'Hauptbahnhof 1, 60329 Frankfurt am Main',
    ]);

    $page = visit(route('meetups.landingpage-event', [
        'country' => 'de',
        'meetup' => $meetup->slug,
        'event' => $event->id,
    ], absolute: false));

    foreach ([[1280, 900], [375, 812]] as [$width, $height]) {
        $m = ($this->measureAt)($page, $width, $height);

        expect($m['overhang'])->toBeLessThanOrEqual(0.5, sprintf(
            'viewport %s: the one-word name ends at x=%s, %spx past the column edge at x=%s',
            $m['viewport'], $m['maxRight'], $m['overhang'], $m['colRight']
        ));
        expect($m['scrollWidth'])->toBeLessThanOrEqual($m['clientWidth']);
    }
});

/*
 * Negative control. Without it the test above proves nothing — a probe that
 * cannot report overflow is green whether the page is broken or not. Here the
 * three utilities that carry the fix are stripped off the live DOM, which is
 * exactly what reverting the Blade file would do, and the very same probe has
 * to see the defect again.
 *
 * Injecting `!important` CSS instead does NOT work in this project: Tailwind v4
 * emits its utilities inside a cascade layer, and for important declarations
 * the layer order is reversed — an unlayered `!important` rule loses against a
 * layered one. Measured: the injected `white-space: nowrap !important` left the
 * computed value at `normal`, and the control passed while proving nothing.
 */
it('would catch the overflow again if the fix were reverted', function () {
    $page = visit($this->eventUrl);

    $healthy = ($this->measureAt)($page, 1280, 900);
    expect($healthy['overhang'])->toBeLessThanOrEqual(0.5);

    $stripped = $page->script(<<<'JS'
        (() => {
          const col = Array.from(document.querySelectorAll('div')).find(
            (d) => typeof d.className === 'string' && d.className.split(/\s+/).includes('md:w-1/3')
          );
          const row = col.firstElementChild;
          row.classList.remove('flex-wrap');
          const textBlock = row.children[1];
          textBlock.className = textBlock.className
            .split(/\s+/)
            .filter((c) => ! c.includes('data-testid') && c !== 'sm:basis-56' && c !== 'sm:grow')
            .join(' ');
          return {row: row.className, textBlock: textBlock.className};
        })()
    JS);

    // The strip has to have hit something — a silent no-op would make the
    // control pass for the wrong reason.
    expect($stripped['row'])->not->toContain('flex-wrap');
    expect($stripped['textBlock'])->not->toContain('data-testid');

    $page->wait(0.5);
    $page->script('document.documentElement.getBoundingClientRect().width');
    $page->wait(0.5);

    $reverted = $page->script($this->probe);

    expect($reverted['overhang'])->toBeGreaterThan(1.0, sprintf(
        'the probe reports %spx overhang on the reverted markup — it cannot see the defect it is meant to pin',
        $reverted['overhang']
    ));
});
