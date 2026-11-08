<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use Database\Seeders\TagSeeder;

/**
 * Verifies the three Flux behaviours the UX concept derived from reading flux.js but
 * could not confirm without a browser:
 *   1. does the search term reset after Flux clears its own input on select
 *   2. does the panel open on focus rather than on click
 *   3. does the resting state really show only the featured tags
 */
beforeEach(function () {
    $this->seed(TagSeeder::class);

    $country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create([
        'city_id' => $city->id,
        'created_by' => actingAsUser()->id,
    ]);
});

it('shows only featured tags in the resting state and reveals the rest on typing', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    $total = $page->script("document.querySelectorAll('.tag-option').length");
    $visible = $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none').length"
    );

    $page->assertNoJavaScriptErrors();

    // 16 event tags are rendered, only the 6 featured ones are visible at rest
    // (issue #149 removed Bitcoin from the resting list).
    expect($total)->toBe(16)
        ->and($visible)->toBe(6);
});

it('opens the panel and keeps the search working across languages', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    // Type the Czech name of a tag whose visible label is German, without diacritics.
    $page->script("document.querySelector('[data-testid=tag-picker] input')?.focus()");
    $page->wait(0.3);
    $page->type('[data-testid=tag-picker] input', 'prednaska');
    $page->wait(0.5);

    $matches = $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none')
            .map(o => o.innerText.trim())"
    );

    $page->assertNoJavaScriptErrors();

    // "Vortrag" must be findable by its Czech alias "Přednáška".
    expect(collect($matches[0])->implode(' | '))->toContain('Vortrag');
});

it('returns to the resting state after a selection', function () {
    // The assumption under test: Flux clears its own input on select (clear="… select")
    // WITHOUT firing an input event. If our x-on:change reset does not compensate, the
    // panel stays stuck in search mode and every later visit shows all 16 tags.
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    $page->script("document.querySelector('[data-testid=tag-picker] input')?.focus()");
    $page->wait(0.3);
    $page->type('[data-testid=tag-picker] input', 'workshop');
    $page->wait(0.5);

    // Click the first still-visible option.
    $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none')[0]?.click()"
    );
    $page->wait(0.8);

    $searching = $page->script("document.querySelector('[data-searching]')?.dataset.searching");
    $visible = $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none').length"
    );

    $page->assertNoJavaScriptErrors();

    expect($searching)->toBe('false')
        ->and($visible)->toBe(6);
});

it('makes the chip remove button reachable and large enough', function () {
    // The last unverified assumption: this markup lives in a <template> that Flux
    // instantiates via cloneNode, so a plain DOM attribute must survive the clone —
    // Alpine bindings are not dependable there.
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    $page->script("document.querySelector('[data-testid=tag-picker] input')?.focus()");
    $page->wait(0.3);
    $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none')[0]?.click()"
    );
    $page->wait(0.8);

    $attrs = $page->script(
        "(() => {
            const b = document.querySelector('ui-selected-remove');
            if (!b) return null;
            const r = b.getBoundingClientRect();
            return {
                tabindex: b.getAttribute('tabindex'),
                role: b.getAttribute('role'),
                label: b.getAttribute('aria-label'),
                onkeydown: typeof b.onkeydown,
                w: Math.round(r.width),
                h: Math.round(r.height),
            };
        })()"
    );

    $page->assertNoJavaScriptErrors();

    expect($attrs)->not->toBeNull();
    expect($attrs['tabindex'])->toBe('0')
        ->and($attrs['role'])->toBe('button')
        ->and($attrs['label'])->not->toBeEmpty()
        ->and($attrs['onkeydown'])->toBe('function');   // survived cloneNode

    // WCAG 2.5.8 asks for 24x24 CSS pixels.
    expect($attrs['w'])->toBeGreaterThanOrEqual(24)
        ->and($attrs['h'])->toBeGreaterThanOrEqual(24);
});

it('removes a chip with the keyboard alone', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    $page->script("document.querySelector('[data-testid=tag-picker] input')?.focus()");
    $page->wait(0.3);
    $page->script(
        "[...document.querySelectorAll('.tag-option')]
            .filter(o => getComputedStyle(o).display !== 'none')[0]?.click()"
    );
    $page->wait(0.8);

    expect($page->script("document.querySelectorAll('ui-selected-remove').length"))->toBe(1);

    // Focus the button and press Enter — no mouse involved.
    $page->script("document.querySelector('ui-selected-remove').focus()");
    $page->wait(0.2);
    $page->script(
        "document.querySelector('ui-selected-remove')
            .dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))"
    );
    $page->wait(0.6);

    $page->assertNoJavaScriptErrors();

    expect($page->script("document.querySelectorAll('ui-selected-remove').length"))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Issue #149 — the picker answers the selection
|--------------------------------------------------------------------------
|
| The definitions list, the commitment badge and the two soft hints are
| server-rendered from tagIds, which only reach the server because the
| pillbox model is .live. That combination — Flux select, Livewire commit,
| re-rendered answer zone — exists only in a real browser; the Livewire
| feature tests short-circuit it at set().
|
*/

/**
 * Opens the panel and clicks one option, addressed by tag id.
 *
 * By id, not by visible text: since issue #149 the option rows carry their
 * DESCRIPTION text, so a substring like "Stammtisch" matches Vortrag's guidance
 * ("…für ein informelles Treffen ohne Programm wähle Stammtisch") before it
 * ever reaches the actual Stammtisch row.
 */
function pickEventTag(object $page, int $tagId): void
{
    $page->click('[data-testid="tag-picker"] input');
    $page->wait(0.4);
    $page->click(sprintf('[data-testid="tag-option-%d"]', $tagId));
    $page->wait(1);

    $page->script(
        "(() => { document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));"
        .' document.activeElement?.blur?.(); return true; })()'
    );
    $page->wait(0.3);
}

function eventTagId(string $germanName): int
{
    $tag = \App\Models\Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (\App\Models\Tag $t): bool => $t->getTranslation('name', 'de') === $germanName);

    abort_if($tag === null, 500, "seeded tag not found: {$germanName}");

    return $tag->id;
}

it('answers a Familien pick with the children hint, live', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    // Familien is not featured, so typing has to reveal it first — the search
    // path a non-featured tag actually requires.
    $page->click('[data-testid="tag-picker"] input');
    $page->wait(0.3);
    $page->type('[data-testid=tag-picker] input', 'familien');
    $page->wait(0.5);
    $page->click(sprintf('[data-testid="tag-option-%d"]', eventTagId('Familien')));
    $page->wait(1);
    $page->script(
        "(() => { document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));"
        .' document.activeElement?.blur?.(); return true; })()'
    );
    $page->wait(0.3);

    $page->assertNoJavaScriptErrors();

    $hint = $page->script(
        "(() => { const h = document.querySelector('[data-testid=family-hint]');
                  return h ? h.textContent.trim() : null; })()"
    );

    expect($hint)->not()->toBeNull()
        ->and($hint)->toContain('was für Kinder da ist');
});

it('answers a format clash with the group hint, and keeps both tags', function () {
    $page = visit("/de/meetup/{$this->meetup->id}/events/create");
    $page->wait(1);

    // Both are featured, so both are clickable at rest.
    pickEventTag($page, eventTagId('Vortrag'));
    pickEventTag($page, eventTagId('Stammtisch'));

    $page->assertNoJavaScriptErrors();

    $hint = $page->script(
        "(() => { const h = document.querySelector('[data-testid=group-hint-format]');
                  const picker = window.Livewire.all().find(c => c.name === 'tags.picker');
                  return { text: h ? h.textContent.trim() : null,
                           tagIds: picker?.snapshot?.data?.tagIds?.[0] ?? null }; })()"
    );

    // Advice, never a block: both selections survive the hint.
    expect($hint['text'])->toContain('Vortrag + Stammtisch')
        ->and($hint['text'])->toContain('prüfe die Format-Tags')
        ->and(count((array) $hint['tagIds']))->toBe(2);
});
