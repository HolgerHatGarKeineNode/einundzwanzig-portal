<?php

use App\Models\User;
use App\Models\WebhookSubscription;

/*
|--------------------------------------------------------------------------
| Issue #55, item 2 — a full sentence inside a flux:badge
|--------------------------------------------------------------------------
|
| The "still blocked" notice on an approved subscription was one 65-character
| sentence rendered inside a `flux:badge`. A badge carries `whitespace-nowrap`
| (flux/badge/index.blade.php:32), so that sentence could not wrap at any
| width, and it sat in a `min-w-0` column that is supposed to shrink.
|
| Measured on the rendered page before the change, 1280px viewport: the row's
| information cell had a min-content width of 484.02px, all of it the badge
| (badge min-content 484.02px), and its natural width had already collapsed
| onto that floor. At 375px the badge ran to x=541.02 while its own card ended
| at x=335 — 206.02px of the sentence outside the card and past the viewport —
| with `documentElement.scrollWidth` still equal to clientWidth, so no
| scrollbar appeared and the text was simply gone. Same silent-overflow
| signature as Issue #66, and the reason a CSS-class assertion cannot stand in
| for this test.
|
*/
beforeEach(function () {
    $this->board = User::factory()->create(['nostr' => config('einundzwanzig.board_members')[0]]);
    $this->actingAs($this->board);

    $owner = User::factory()->create(['name' => 'Satoshi']);

    $this->subscription = WebhookSubscription::factory()->create([
        'user_id' => $owner->id,
        'url' => 'https://1.1.1.1/hooks/incoming',
        'resources' => ['meetup', 'meetup-event'],
        'approved_at' => now(),
        'active' => false,
    ]);
});

$measureScript = <<<'JS'
    (() => {
        const round = (n) => Math.round(n * 100) / 100;
        const minContent = (el) => {
            const previous = el.style.width;
            el.style.width = 'min-content';
            void el.getBoundingClientRect().width;
            const value = el.getBoundingClientRect().width;
            el.style.width = previous;
            void el.getBoundingClientRect().width;
            return round(value);
        };

        const cell = document.querySelector('[data-testid^="admin-webhooks-approved-info-"]');
        const notice = cell.querySelector('[data-testid^="admin-webhooks-blocked-"]');
        const badge = notice.querySelector('[data-flux-badge]');
        const explanation = notice.querySelector('[data-flux-text]');
        const card = cell.parentElement.parentElement;

        return {
            cellMinContent: minContent(cell),
            badgeMinContent: minContent(badge),
            badgeText: badge.textContent.trim(),
            explanationWhiteSpace: getComputedStyle(explanation).whiteSpace,
            explanationMinContent: minContent(explanation),
            noticeRight: round(notice.getBoundingClientRect().right),
            cardRight: round(card.getBoundingClientRect().right),
            overhangPastCard: round(notice.getBoundingClientRect().right - card.getBoundingClientRect().right),
            viewportWidth: document.documentElement.clientWidth,
        };
    })()
JS;

it('does not let the blocked notice set a rigid minimum width on the approved row', function () use ($measureScript) {
    $page = visit('/de/admin/webhooks');
    $page->resize(1280, 900)->wait(1.2);

    $measured = $page->script($measureScript);

    // The badge holds one word now, and that word is the same one the owner
    // sees for this state on settings/webhooks.blade.php.
    expect($measured['badgeText'])->toBe('Pausiert');

    // 73.61px measured for "Pausiert" — the old sentence measured 484.02px.
    // 150px is the ceiling any of the two labels may reach, in any locale,
    // before it is a sentence again.
    expect($measured['badgeMinContent'])->toBeLessThan(150);

    // The cell's floor is now the URL line (`truncate` implies
    // `white-space: nowrap`), measured at 252.05px, not the notice. The badge
    // is no longer the binding constraint, which is the whole point.
    expect($measured['cellMinContent'])->toBeLessThan(300)
        ->and($measured['badgeMinContent'])->toBeLessThan($measured['cellMinContent']);

    // The sentence itself has to be able to wrap.
    expect($measured['explanationWhiteSpace'])->not->toBe('nowrap');
    expect($measured['explanationMinContent'])->toBeLessThan(150);
});

it('keeps the blocked notice inside its card at 375px', function () use ($measureScript) {
    $page = visit('/de/admin/webhooks');
    $page->resize(375, 812)->wait(1.2);

    $measured = $page->script($measureScript);

    expect($measured['viewportWidth'])->toBe(375);
    expect($measured['overhangPastCard'])->toBeLessThanOrEqual(0);
    expect($measured['noticeRight'])->toBeLessThanOrEqual(375);
});

/*
 * Negative control. Putting the sentence back into the badge on the live DOM
 * has to reproduce the reported geometry — otherwise the two tests above are
 * measuring something that was never able to fail.
 */
it('reproduces the reported overflow when the sentence is put back into the badge', function () {
    $page = visit('/de/admin/webhooks');
    $page->resize(375, 812)->wait(1.2);

    $reproduced = $page->script(<<<'JS'
        (() => {
            const round = (n) => Math.round(n * 100) / 100;
            const cell = document.querySelector('[data-testid^="admin-webhooks-approved-info-"]');
            const notice = cell.querySelector('[data-testid^="admin-webhooks-blocked-"]');
            const badge = notice.querySelector('[data-flux-badge]');
            const explanation = notice.querySelector('[data-flux-text]');
            const card = cell.parentElement.parentElement;

            const sentence = explanation.textContent.trim();
            explanation.remove();
            badge.textContent = sentence;
            void badge.getBoundingClientRect().width;

            const previous = badge.style.width;
            badge.style.width = 'min-content';
            void badge.getBoundingClientRect().width;
            const badgeMinContent = round(badge.getBoundingClientRect().width);
            badge.style.width = previous;
            void badge.getBoundingClientRect().width;

            return {
                badgeMinContent: badgeMinContent,
                badgeWidth: round(badge.getBoundingClientRect().width),
                badgeWhiteSpace: getComputedStyle(badge).whiteSpace,
                overhangPastCard: round(badge.getBoundingClientRect().right - card.getBoundingClientRect().right),
                documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            };
        })()
    JS);

    // The badge cannot shrink below the whole sentence — `min-content` and its
    // laid-out width are the same number, which is what "rigid" means here.
    expect($reproduced['badgeWhiteSpace'])->toBe('nowrap');
    expect($reproduced['badgeMinContent'])->toBeGreaterThan(400)
        ->and($reproduced['badgeMinContent'])->toBe($reproduced['badgeWidth']);

    // 206.02px past the card was the number measured on the real page before
    // the fix, at this exact viewport.
    expect($reproduced['overhangPastCard'])->toBeGreaterThan(150);

    // And the reason nobody saw it: a nowrap child overflowing its block does
    // not grow the document, so no scrollbar ever appears.
    expect($reproduced['documentOverflow'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Issue #55, item 3 — one empty-state component, not two
|--------------------------------------------------------------------------
|
| The page used `flux:callout` for the empty pending queue and `flux:text` for
| the empty approved list. A callout is an attention device; an empty approval
| queue is the good outcome. Both are `flux:text` now. The success flash above
| them stays a callout — that one does report something that just changed.
|
*/
it('renders both empty states with the same component', function () {
    WebhookSubscription::query()->delete();

    $page = visit('/de/admin/webhooks');
    $page->resize(1280, 900)->wait(1.2);

    $emptyStates = $page->script(<<<'JS'
        (() => {
            const pending = document.querySelector('[data-testid="admin-webhooks-pending-empty"]');
            const approved = Array.from(document.querySelectorAll('[data-flux-text]'))
                .find((el) => el.textContent.includes('Noch keine Subscription freigeschaltet'));

            return {
                pendingTag: pending ? pending.tagName.toLowerCase() : null,
                pendingIsFluxText: pending ? pending.hasAttribute('data-flux-text') : false,
                pendingIsCallout: pending ? pending.closest('[data-flux-callout]') !== null : false,
                approvedTag: approved ? approved.tagName.toLowerCase() : null,
                calloutsOnPage: document.querySelectorAll('[data-flux-callout]').length,
            };
        })()
    JS);

    expect($emptyStates['pendingIsFluxText'])->toBeTrue()
        ->and($emptyStates['pendingIsCallout'])->toBeFalse()
        ->and($emptyStates['pendingTag'])->toBe($emptyStates['approvedTag']);

    // No flash is in play here, so the page should carry no callout at all.
    expect($emptyStates['calloutsOnPage'])->toBe(0);
});
