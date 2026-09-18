<?php

require_once __DIR__.'/../Support/PixelContrast.php';

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\Tag;
use Database\Seeders\TagSeeder;

/*
|--------------------------------------------------------------------------
| Issue #149 — the commitment badge, measured at the pixel
|--------------------------------------------------------------------------
|
| The badge tells an organiser that the tag they are choosing is a promise to
| attendees, so it has to survive both themes at WCAG 1.4.3's 4.5:1 — 12px at
| weight 500 (flux:badge size="sm") is not large text. Computed from the
| tokens, Flux's default zinc badge lands at 9.25:1 light and 5.57:1 dark; the
| numbers below are the same measurement the eye receives, because the wash is
| an alpha colour the compositor resolves over whatever the page provides
| (computed-from-token was measured ~9 % off in dark on this repo, see
| PixelContrast.php's header).
|
| Amber was considered and rejected for this badge: amber-700 on the amber wash
| measures 4.4:1 light — the exact failure mode of issue #98's webhook badge.
| A promise is information, not a warning; zinc carries it safely.
|
| Theme switching toggles `dark` on <html>, which is what @fluxAppearance's own
| script does. The negative control at the end puts a weak colour back and has
| to reproduce a failure, or the two tests above measured nothing.
*/

beforeEach(function () {
    $this->seed(TagSeeder::class);

    $country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create([
        'city_id' => $city->id,
        'created_by' => actingAsUser()->id,
    ]);

    $this->beginners = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $tag): bool => $tag->getTranslation('name', 'de') === 'Einsteiger');
});

/**
 * Picks the Beginners tag through the real Flux combobox, so the definition
 * list and its badge exist exactly the way an organiser would see them.
 *
 * Einsteiger is featured, so it is offered at rest: opening the panel and
 * clicking needs no typed search, which keeps the interaction the same one the
 * chip tests already exercise. (The typed path works too — but typing
 * "einsteiger" also matches Fortgeschrittene through its DESCRIPTION text,
 * and pinning option order through someone else's guidance copy would make
 * this test fragile for no reason.)
 */
function pickBeginnersTag(object $page, int $tagId): void
{
    $page->click('[data-testid="tag-picker"] input');
    $page->wait(0.4);
    $page->click(sprintf('[data-testid="tag-option-%d"]', $tagId));
    $page->wait(1);

    // Close the combobox panel before anything measures pixels: an open Flux
    // popover keeps hover targets underneath it unreachable, and the pick has
    // already reached the server by now anyway.
    $page->script(
        "(() => { document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));"
        ." document.activeElement?.blur?.(); return true; })()"
    );
    $page->wait(0.3);
}

it('shows the definitions and the commitment badge after picking the tag', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    pickBeginnersTag($page, $this->beginners->id);

    $page->assertNoJavaScriptErrors();

    // The guidance follows the choice: the definition row and the badge exist,
    // and both are actually painted, not merely in the DOM.
    $visible = $page->script(
        "(() => {
            const definition = document.querySelector('[data-testid=\"tag-definition-{$this->beginners->id}\"]');
            const badge = document.querySelector('[data-testid=\"definition-commitment-{$this->beginners->id}\"]');
            return {
                definition: definition ? getComputedStyle(definition).display !== 'none' : false,
                badge: badge ? getComputedStyle(badge).display !== 'none' : false,
                label: badge?.textContent.trim() ?? '',
            };
        })()"
    );

    expect($visible['definition'])->toBeTrue()
        ->and($visible['badge'])->toBeTrue()
        ->and($visible['label'])->toBe('Versprechen an Besucher');
});

it('renders the commitment badge above 4.5:1 on the light page', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->resize(1280, 900)->wait(1.2);

    pickBeginnersTag($page, $this->beginners->id);
    pixelSwitchTheme($page, 'light');

    $measured = pixelMeasureText(
        $page,
        sprintf('[data-testid="definition-commitment-%d"]', $this->beginners->id),
        'issue-149-commitment-badge-light',
    );

    // WCAG 1.4.3. Computed 9.25:1 (zinc-700 #3f3f46 on zinc-400/15 over white
    // = #f1f1f2); the 7.5 floor keeps headroom for font rendering while staying
    // far above anything a weak colour could reach. The fill is deliberately
    // NOT byte-pinned: this rig's Chromium composites alpha washes one count
    // off per channel from what the tokens promise (measured on the webhook
    // badge, #FFEDBF vs #FFEEBF), so a byte pin would test the browser build
    // and not the badge.
    expect($measured['gd']['ratio'])->toBeGreaterThanOrEqual(4.5)
        ->and($measured['gd']['ratio'])->toBeGreaterThan(7.5);
});

it('renders the commitment badge above 4.5:1 on the dark page', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->resize(1280, 900)->wait(1.2);

    pickBeginnersTag($page, $this->beginners->id);
    pixelSwitchTheme($page, 'dark');

    $measured = pixelMeasureText(
        $page,
        sprintf('[data-testid="definition-commitment-%d"]', $this->beginners->id),
        'issue-149-commitment-badge-dark',
    );

    // Computed 5.57:1 (zinc-200 #e4e4e7 on zinc-400/40 over the zinc-800 page
    // = #58585d); the 4.8 floor sits above 4.5:1 with room for the
    // glyph-recovery method to land a shade off. No byte pin — see light test.
    expect($measured['gd']['ratio'])->toBeGreaterThanOrEqual(4.5)
        ->and($measured['gd']['ratio'])->toBeGreaterThan(4.8);
});

it('reproduces a failure when a weak colour is put back', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->resize(1280, 900)->wait(1.2);

    pickBeginnersTag($page, $this->beginners->id);
    pixelSwitchTheme($page, 'light');

    $selector = sprintf('[data-testid="definition-commitment-%d"]', $this->beginners->id);

    // zinc-400 text on the light wash is a pair a real badge design could drift
    // into. Inline style rather than a utility class: Tailwind only emits a
    // class some source still uses, and two same-specificity colour utilities
    // resolve by stylesheet order, not by class order — an inline style is the
    // one injection whose effect cannot be silently overridden. If the probe
    // cannot see THIS, it cannot see anything.
    $applied = $page->script(
        '(() => { const b = document.querySelector('.json_encode($selector).');'
        ." b.style.color = '#a1a1aa'; void b.offsetHeight;"
        .' return getComputedStyle(b).color; })()'
    );
    $page->wait(0.3);

    expect($applied)->not()->toBe('#3f3f46');

    $measured = pixelMeasureText($page, $selector, 'issue-149-commitment-badge-control');

    expect($measured['gd']['ratio'])->toBeLessThan(4.5);
});
