<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;

/*
 * Long unbreakable tokens (npub, naddr, invite URLs) used to push the meetup
 * landing page past the viewport on mobile. The Würzburg report was a 63-char
 * npub sitting in the intro markdown — `break-all` on the dedicated <code>
 * next to it never reached that string, and `overflow-wrap: break-word` does
 * not lower min-content. `overflow-wrap: anywhere` does (Issue #66).
 *
 * Geometry, not a class assertion: a CSS-class check would stay green while
 * the glyphs still ran past the card, which is how the original report looked.
 */

const OVERFLOW_NPUB = 'npub1pt0kw36ue3w2g4haxq3wgm6a2fhtptmzsjlc2j2vphtcgle72qesgpjyc6';

const OVERFLOW_PUBKEY = '45df061ee03c855bdd2c3ecea528d5725e3331e465cd38f14bfe403422952a03';

const OVERFLOW_TELEGRAM = 'https://t.me/+rP-abcdefghijklmnopqrstuvwxyzABCDEF';

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Würzburg',
    ]);
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'EINUNDZWANZIG WÜRZBURG',
        'slug' => 'einundzwanzig-wuerzburg-overflow',
        'nostr' => OVERFLOW_NPUB,
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => '31924:'.OVERFLOW_PUBKEY.':meetup-overflow',
        'telegram_link' => OVERFLOW_TELEGRAM,
        'intro' => "Link zum Meetup\n".OVERFLOW_TELEGRAM."\n".OVERFLOW_NPUB,
    ]);
    $this->event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => now()->addWeek(),
        'description' => 'Kontakt: '.OVERFLOW_NPUB,
        'nostr_coordinate' => '31923:'.OVERFLOW_PUBKEY.':meetup-event-overflow',
    ]);

    $this->landingUrl = route('meetups.landingpage', [
        'country' => 'de',
        'meetup' => $this->meetup->slug,
    ], absolute: false);

    $this->eventUrl = route('meetups.landingpage-event', [
        'country' => 'de',
        'meetup' => $this->meetup->slug,
        'event' => $this->event->id,
    ], absolute: false);

    /*
     * Leaflet tiles sit past the map pane and are clipped by overflow:hidden.
     * Raw rects of those images would fail every assertion here on invisible
     * boxes, so each rect is clipped against overflow-x ancestors first —
     * the same thing the eye does. Copied from EventHeaderFitsColumnTest.
     */
    $this->probe = <<<'JS'
        (() => {
          const clippedRight = (el) => {
            let right = el.getBoundingClientRect().right;
            let p = el.parentElement;
            while (p) {
              if (getComputedStyle(p).overflowX !== 'visible') {
                right = Math.min(right, p.getBoundingClientRect().right);
              }
              p = p.parentElement;
            }
            return right;
          };
          const tokens = [];
          const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
          while (walker.nextNode()) {
            const text = walker.currentNode.textContent || '';
            if (! /npub1|naddr1|https:\/\/t\.me\//.test(text)) {
              continue;
            }
            const el = walker.currentNode.parentElement;
            if (! el) {
              continue;
            }
            const r = el.getBoundingClientRect();
            if (r.width === 0 && r.height === 0) {
              continue;
            }
            tokens.push({
              tag: el.tagName,
              right: Math.round(clippedRight(el) * 100) / 100,
              text: text.replace(/\s+/g, ' ').trim().slice(0, 36),
            });
          }
          return {
            viewport: window.innerWidth,
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
            tokenCount: tokens.length,
            worstRight: tokens.length ? Math.max(...tokens.map((t) => t.right)) : 0,
            worst: tokens.sort((a, b) => b.right - a.right).slice(0, 5),
          };
        })()
    JS;

    $this->measureAt = function ($page, int $width, int $height) {
        $page->resize($width, $height)->wait(1.2);
        $page->script('document.documentElement.getBoundingClientRect().width');
        $page->wait(0.5);

        return $page->script($this->probe);
    };
});

it('keeps npub, naddr and invite URLs inside the viewport on mobile', function () {
    $page = visit($this->landingUrl);

    foreach ([[375, 812], [390, 844]] as [$width, $height]) {
        $m = ($this->measureAt)($page, $width, $height);

        expect($m['tokenCount'])->toBeGreaterThan(0, sprintf(
            'viewport %s: probe found no npub/naddr/telegram tokens',
            $m['viewport']
        ));

        expect($m['scrollWidth'])->toBeLessThanOrEqual(
            $m['clientWidth'],
            sprintf(
                'viewport %s: document scrolls horizontally (%s > %s) — %s',
                $m['viewport'], $m['scrollWidth'], $m['clientWidth'], json_encode($m['worst'])
            )
        );

        expect($m['worstRight'])->toBeLessThanOrEqual(
            $m['viewport'] + 0.5,
            sprintf(
                'viewport %s: a token ends at x=%s, past the viewport — %s',
                $m['viewport'], $m['worstRight'], json_encode($m['worst'])
            )
        );
    }

    $page->assertNoJavaScriptErrors();
});

it('keeps the event-page naddr and description npub inside the viewport', function () {
    $page = visit($this->eventUrl);

    $m = ($this->measureAt)($page, 375, 812);

    expect($m['tokenCount'])->toBeGreaterThan(0);
    expect($m['scrollWidth'])->toBeLessThanOrEqual($m['clientWidth'], json_encode($m['worst']));
    expect($m['worstRight'])->toBeLessThanOrEqual($m['viewport'] + 0.5, json_encode($m['worst']));
});

/*
 * Negative control. Without it the tests above prove nothing — a probe that
 * cannot report overflow is green whether the page is broken or not. The
 * wrap-anywhere utilities AND the html { overflow-wrap: anywhere } rule are
 * stripped off the live DOM; the same probe has to see the defect again.
 *
 * Class stripping, not injected CSS: Tailwind v4 emits utilities inside a
 * cascade layer, and an unlayered !important loses against a layered one
 * (measured in EventHeaderFitsColumnTest).
 */
it('would catch the overflow again if the wrap were reverted', function () {
    $page = visit($this->landingUrl);

    $healthy = ($this->measureAt)($page, 375, 812);
    expect($healthy['scrollWidth'])->toBeLessThanOrEqual($healthy['clientWidth']);
    expect($healthy['worstRight'])->toBeLessThanOrEqual($healthy['viewport'] + 0.5);

    $stripped = $page->script(<<<'JS'
        (() => {
          const npub = document.querySelector('[data-testid="meetup-nostr-npub"]');
          if (! npub) {
            return {found: false};
          }
          npub.classList.remove('wrap-anywhere', 'max-w-full', 'min-w-0', 'break-all');
          npub.style.whiteSpace = 'nowrap';
          npub.style.overflowWrap = 'normal';
          npub.style.wordBreak = 'normal';
          npub.style.maxWidth = 'none';
          npub.style.width = 'max-content';
          document.documentElement.style.overflowWrap = 'normal';
          return {found: true, className: npub.className};
        })()
    JS);

    expect($stripped['found'])->toBeTrue();
    expect($stripped['className'])->not->toContain('wrap-anywhere');

    $page->wait(0.5);
    $page->script('document.documentElement.getBoundingClientRect().width');
    $page->wait(0.5);

    $reverted = $page->script(<<<'JS'
        (() => {
          const npub = document.querySelector('[data-testid="meetup-nostr-npub"]');
          const r = npub.getBoundingClientRect();
          return {
            width: Math.round(r.width * 100) / 100,
            right: Math.round(r.right * 100) / 100,
            viewport: window.innerWidth,
          };
        })()
    JS);

    expect($reverted['width'])->toBeGreaterThan(400, sprintf(
        'the unwrapped npub is %spx wide (viewport %s) — the probe cannot see the defect it is meant to pin',
        $reverted['width'], $reverted['viewport']
    ));
});
