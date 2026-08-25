<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\Tag;
use Database\Seeders\TagSeeder;

/*
|--------------------------------------------------------------------------
| What only a browser can answer
|--------------------------------------------------------------------------
|
| Four questions the server-side tests cannot settle:
|
|   1. Does the whole seeded vocabulary survive a real render? An icon name Flux
|      cannot resolve is an exception, and the moderation screen draws every tag
|      at once — the exact page the plan says must not blow up.
|   2. Is the ordering reachable without a pointer? WCAG 2.5.7 asks for a
|      single-pointer alternative to the drag gesture and 2.1.1 for keyboard
|      operability. Playwright's press() dispatches real key events and never
|      touches the mouse, so a green run here is an answer and not a proxy.
|   3. Does the freshly featured tag show up in the picker's RESTING state? That
|      state is a CSS rule, and CSS is the one thing a Livewire test cannot see.
|   4. Does the icon picker really rest on sixteen names? Same kind of question:
|      all fifty-two options are in the DOM, and only computed display tells the
|      two states apart.
|
| NOT covered here: the drag gesture itself. Simulating SortableJS through
| synthetic pointer moves is flaky enough to be worth less than it costs, and
| the equivalence it would show is already established at the door both paths go
| through — `wire:sort="reorder($item, $position)"` calls the very action the
| arrow buttons call (TagModerationTest, "produces the same order whether dragged
| or moved with the buttons").
|
*/

beforeEach(function () {
    $this->seed(TagSeeder::class);

    $this->editor = actingAsUser(['nostr' => config('einundzwanzig.tag_editors')[0]]);
});

it('renders the whole vocabulary without a view exception', function () {
    $expected = Tag::query()->count();

    $page = visit('/de/tags/moderation');
    $page->wait(1);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Tags verwalten');

    // The icon name is nowhere in the HTML once Flux has inlined the SVG, so the
    // count comes off the hook the partial writes, not off the markup Flux emits.
    $rendered = $page->script("document.querySelectorAll('[data-tag-icon]').length");
    $fallbacks = $page->script("document.querySelectorAll('[data-tag-icon-fallback]').length");

    // script() hands back the evaluated value itself, not a one-element wrapper.
    expect($rendered)->toBe($expected)
        ->and($fallbacks)->toBe(0);
});

it('walks a tag from the last position to the first with the keyboard alone', function () {
    $featured = Tag::query()
        ->approved()
        ->where('type', 'meetup_event')
        ->where('featured', true)
        ->ordered()
        ->get();

    expect($featured)->toHaveCount(7);

    $last = $featured->last();

    $page = visit('/de/tags/moderation');
    $page->wait(1);

    // press() focuses the element and dispatches keydown/keypress/keyup. No click,
    // no pointer — this is the 2.1.1 path, not a click dressed up as one.
    $page->keys('#move-up-'.$last->id, 'Enter');
    $page->wait(0.5);

    // The row moved, so the button is a different node in a different place. If
    // focus were not put back, the next keystroke would go to <body> and a keyboard
    // user would have to hunt for the row again after every single step (2.4.3).
    $focused = $page->script('document.activeElement?.id');

    expect($focused)->toBe('move-up-'.$last->id);

    foreach (range(1, 5) as $ignored) {
        $page->keys('#move-up-'.$last->id, 'Enter');
        $page->wait(0.4);
    }

    $page->assertNoJavaScriptErrors();

    $order = Tag::query()
        ->approved()
        ->where('type', 'meetup_event')
        ->where('featured', true)
        ->ordered()
        ->pluck('id')
        ->all();

    expect($order[0])->toBe($last->id)
        ->and($last->fresh()->order_column)->toBe(1);

    // The status line has to say so as well; a move nobody can perceive is not a
    // move that was made (WCAG 4.1.3).
    $status = $page->script("document.querySelector('[data-testid=reorder-status]')?.textContent.trim()");

    expect($status)->toContain('Position 1 von 7');
});

it('shows a newly featured tag in the picker before anyone types', function () {
    $country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'created_by' => $this->editor->id]);

    $newcomer = Tag::query()
        ->approved()
        ->where('type', 'meetup_event')
        ->where('featured', false)
        ->ordered()
        ->firstOrFail();

    $moderation = visit('/de/tags/moderation');
    $moderation->wait(1);
    $moderation->click('[data-testid=featured-'.$newcomer->id.']');
    $moderation->wait(0.6);
    $moderation->assertNoJavaScriptErrors();

    expect($newcomer->fresh()->featured)->toBeTrue();

    // The resting state is the CSS rule in the picker; only a browser can confirm
    // that the new row is genuinely visible without a keystroke.
    $picker = visit("/de/meetup/{$meetup->id}/events/create");
    $picker->wait(1);

    $visible = $picker->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none')
            .map(o => o.dataset.testid)"
    );

    $picker->assertNoJavaScriptErrors();

    expect($visible)->toContain('tag-option-'.$newcomer->id)
        ->and($visible)->toHaveCount(8);
});

it('offers the sixteen names in use at rest and the rest on typing', function () {
    // The same resting/searching split the tag picker uses, driven by Flux's own
    // signal (`input:placeholder-shown`) rather than a second piece of state. Only a
    // browser can answer it: the rule is CSS, and every one of the fifty-two options
    // is in the DOM either way.
    $featured = Tag::query()
        ->approved()
        ->where('type', 'meetup_event')
        ->where('featured', true)
        ->ordered()
        ->firstOrFail();

    $page = visit('/de/tags/moderation');
    $page->wait(1);
    $page->click('[data-testid=edit-'.$featured->id.']');
    $page->wait(0.8);
    $page->click('[data-testid=icon-select]');
    $page->wait(0.6);

    $total = $page->script("document.querySelectorAll('.icon-option').length");
    $atRest = $page->script(
        "[...document.querySelectorAll('.icon-option')]
            .filter(o => getComputedStyle(o).display !== 'none').length"
    );

    expect($total)->toBe(count(config('einundzwanzig.tag_icons')))
        ->and($atRest)->toBe(count(config('einundzwanzig.tag_icons_common')));

    // `wallet` is deliberately NOT one of the common sixteen — if it showed up
    // without typing, the split would be decorative.
    $page->script(
        "const i = [...document.querySelectorAll('[data-flux-select-search] input')]
            .find(x => x.placeholder === 'Suchen');
         i.focus(); i.value = 'wall';
         i.dispatchEvent(new Event('input', { bubbles: true }));"
    );
    $page->wait(0.6);

    $found = $page->script(
        "[...document.querySelectorAll('.icon-option')]
            .filter(o => getComputedStyle(o).display !== 'none')
            .map(o => o.textContent.trim())"
    );

    $page->assertNoJavaScriptErrors();

    expect($found)->toContain('wallet');
});
