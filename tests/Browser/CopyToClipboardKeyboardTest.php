<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Support\NostrCalendarAddress;
use Pest\Browser\Api\PendingAwaitablePage;

/*
|--------------------------------------------------------------------------
| #80 — the clipboard directive has to answer the keyboard
|--------------------------------------------------------------------------
|
| The directive used to bind `click` only, in three literal copies. The
| elements carrying it are marked `role="button" tabindex="0"`, so focus lands
| on them and then nothing happens — WCAG 2.1.1. The existing coverage clicks,
| which is exactly why it survived, so every assertion below drives the control
| with a key press and never with `click()`.
|
| Two shapes are covered because they take different paths through the
| directive:
|
|   <code role="button" tabindex="0">   no native activation — the directive's
|                                       own keydown handler must copy
|   <button> (flux:button)              the browser turns Enter/Space into a
|                                       click itself, so the directive must NOT
|                                       handle keydown as well, or one key
|                                       press copies twice
|
| They also sit on the two layouts a route can actually reach:
| components.layouts.app.sidebar (meetup landing page) and
| components.layouts.auth.simple (/ki-assistent). The third former copy,
| components.layouts.app.header, has no route in this application — the last
| test guards it by source instead.
|
| The pubkey is a throwaway, used only to build a syntactically valid
| coordinate (the same one as tests/Feature/Meetups/NostrCalendarAddressDisplayTest).
*/

const KEYBOARD_PUBKEY = '45df061ee03c855bdd2c3ecea528d5725e3331e465cd38f14bfe403422952a03';

/**
 * Replaces the clipboard sink and records what the directive hands it.
 *
 * The real clipboard needs a permission grant this headless run does not have, and
 * asserting on the system clipboard would test the browser rather than the directive.
 * What is under test is whether a key press reaches the handler at all.
 */
function withRecordingClipboard(PendingAwaitablePage $page): PendingAwaitablePage
{
    $page->script(<<<'JS'
        (() => {
            window.__clipboardWrites = [];
            window.__lastKeydownDefaultPrevented = {};

            Object.defineProperty(navigator, 'clipboard', {
                configurable: true,
                value: {
                    writeText: (text) => {
                        window.__clipboardWrites.push(text);

                        return Promise.resolve();
                    },
                },
            });

            // Bubble phase, so this runs after the element's own handler and reports
            // whether the directive cancelled the browser default (Space scrolls).
            document.addEventListener('keydown', (event) => {
                window.__lastKeydownDefaultPrevented[event.key] = event.defaultPrevented;
            });
        })()
    JS);

    return $page;
}

/**
 * A published meetup, so <x-nostr-calendar-address> renders its copyable address.
 */
function meetupWithNostrAddress(): Meetup
{
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);

    return Meetup::factory()->create([
        'city_id' => $city->id,
        'visible_on_map' => true,
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => '31924:'.KEYBOARD_PUBKEY.':meetup-80',
    ]);
}

it('copies with Enter on the role=button address', function () {
    $meetup = meetupWithNostrAddress();
    $expected = NostrCalendarAddress::fromCoordinate($meetup->nostr_coordinate)->naddr();

    $page = withRecordingClipboard(visit("/de/meetup/{$meetup->slug}"));

    $page->assertVisible('[data-testid="nostr-calendar-address"] code')
        ->keys('[data-testid="nostr-calendar-address"] code', 'Enter');

    expect($page->script('window.__clipboardWrites'))->toBe([$expected]);

    $page->assertNoJavaScriptErrors();
});

it('copies with Space on the role=button address without scrolling the page', function () {
    $meetup = meetupWithNostrAddress();
    $expected = NostrCalendarAddress::fromCoordinate($meetup->nostr_coordinate)->naddr();

    $page = withRecordingClipboard(visit("/de/meetup/{$meetup->slug}"));

    $page->assertVisible('[data-testid="nostr-calendar-address"] code')
        ->keys('[data-testid="nostr-calendar-address"] code', 'Space');

    expect($page->script('window.__clipboardWrites'))->toBe([$expected]);

    // Space scrolls by default. Without preventDefault() the page jumps a viewport
    // while copying, which is why the directive cancels it.
    expect($page->script("window.__lastKeydownDefaultPrevented[' ']"))->toBeTrue();
    expect($page->script('window.scrollY'))->toBe(0);

    $page->assertNoJavaScriptErrors();
});

it('copies exactly once per key press on a real button and leaves its native activation alone', function (string $key, string $eventKey) {
    $page = withRecordingClipboard(visit('/ki-assistent'));

    $page->assertVisible('button[x-copy-to-clipboard]')
        ->keys('button[x-copy-to-clipboard]', $key);

    $writes = $page->script('window.__clipboardWrites');

    expect($writes)->toHaveCount(1)
        ->and($writes[0])->toEndWith('/mcp');

    /*
     * A native <button> already turns Enter and Space into a click on its own, so the
     * directive skips its keydown handler here. That the browser default survived
     * untouched is what proves the skip happened: the handler's first act is
     * preventDefault(), so without the guard this would read true — and the copy above
     * would then be the handler's, not the button's.
     */
    expect($page->script(sprintf('window.__lastKeydownDefaultPrevented[%s]', json_encode($eventKey))))
        ->toBeFalse();

    $page->assertNoJavaScriptErrors();
})->with([
    // [key sent to Playwright, resulting KeyboardEvent.key]
    ['Enter', 'Enter'],
    ['Space', ' '],
]);

it('hands the translated toast copy to the directive on the sidebar layout', function () {
    $meetup = meetupWithNostrAddress();

    $page = visit("/de/meetup/{$meetup->slug}");

    // The strings used to be inlined per layout; they now come from one partial. If a
    // layout loses the include, the toast silently falls back to English.
    expect($page->script('typeof window.clipboardToastMessages'))->toBe('object')
        ->and($page->script('typeof window.clipboardToastMessages.heading'))->toBe('string')
        ->and($page->script('typeof window.clipboardToastMessages.text'))->toBe('string');
});

it('hands the translated toast copy to the directive on the auth layout', function () {
    $page = visit('/ki-assistent');

    expect($page->script('typeof window.clipboardToastMessages'))->toBe('object')
        ->and($page->script('typeof window.clipboardToastMessages.heading'))->toBe('string')
        ->and($page->script('typeof window.clipboardToastMessages.text'))->toBe('string');
});

it('keeps the directive in one place and every layout wired to it', function () {
    /*
     * components.layouts.app.header has no route in this application, so no browser
     * test can reach it — and it was one of the three copies. A source guard is the
     * only thing that stops the next edit from re-inlining a fourth copy that then
     * drifts, which is the failure mode the issue describes.
     */
    $layouts = [
        base_path('resources/views/components/layouts/app/sidebar.blade.php'),
        base_path('resources/views/components/layouts/app/header.blade.php'),
        base_path('resources/views/components/layouts/auth/simple.blade.php'),
    ];

    foreach ($layouts as $layout) {
        $contents = file_get_contents($layout);

        // str_contains() rather than expect()->not->toContain(): that matcher is
        // variadic, so a second argument becomes another needle instead of a message
        // and the negated assertion passes on the message alone.
        expect(str_contains($contents, "Alpine.directive('copy-to-clipboard'"))
            ->toBeFalse($layout.' inlines its own copy of the clipboard directive again.');

        expect(str_contains($contents, "@include('partials.clipboard-toast-messages')"))
            ->toBeTrue($layout.' no longer includes the clipboard toast copy.');
    }

    expect(str_contains(
        file_get_contents(base_path('resources/js/copyToClipboard.js')),
        "Alpine.directive('copy-to-clipboard'",
    ))->toBeTrue('The shared clipboard directive is gone from resources/js/copyToClipboard.js.');
});
