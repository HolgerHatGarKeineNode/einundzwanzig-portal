<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Livewire\Livewire;

/*
 * Issue #73: the sidebar's language select bound `session('lang_country')` straight to a
 * `flux:select` whose options are a strict SUBSET of the locales that session key may
 * legitimately hold. `config('lang-country.allowed')` lists 33 locales; the select only
 * offers the ones whose language has a `lang/*.json` file — 17 today. For the other 16,
 * Flux' `ui-selected` element finds no `<ui-option>` for the bound value and throws
 * `Uncaught Could not find option for value "…"` while rendering, on every page that
 * draws the sidebar. That is why `assertNoJavascriptErrors()` could not be used on the
 * meetup pages without an allowance.
 *
 * `fr-BE` is the cheapest reachable case: it is in `allowed` (so the switch route accepts
 * it), it is exactly what `Accept-Language: fr` resolves to (measured), and there is no
 * `lang/fr.json`, so the select cannot offer it.
 */
beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Frankfurt am Main',
    ]);
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Issue 73 Sprachwahl Meetup',
        'slug' => 'issue-73-language-selector',
        'visible_on_map' => true,
    ]);
    $this->event = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);

    $this->urls = [
        'meetup list' => route('meetups.index', ['country' => 'de'], absolute: false),
        'meetup detail' => route('meetups.landingpage', [
            'country' => 'de',
            'meetup' => $this->meetup->slug,
        ], absolute: false),
        'event detail' => route('meetups.landingpage-event', [
            'country' => 'de',
            'meetup' => $this->meetup->slug,
            'event' => $this->event->id,
        ], absolute: false),
    ];

    $this->switchUrl = route('lang_country.switch', ['lang_country' => 'fr-BE'], absolute: false);
});

it('renders without JavaScript errors on a locale the language select cannot offer', function (string $page) {
    $url = $this->urls[$page];

    $browser = visit($url);
    $browser->assertSee($this->meetup->name);

    /*
     * The switch route answers with `redirect()->back()`, so coming from $url it lands
     * back on $url: the session locale changes without any other page rendering in
     * between. That matters — every page with the sidebar (and the welcome page) carries
     * the same select, and its errors would land in this same console and make the
     * assertion below pass or fail for the wrong page.
     */
    $browser->script('window.location.href = "'.$this->switchUrl.'"');
    $browser->assertSee($this->meetup->name);

    // Without this the assertion below would also hold on a page that renders no select
    // at all — a sidebar that stops rendering must not read as "fixed".
    expect($browser->script('document.querySelectorAll("ui-select").length'))
        ->toBeGreaterThan(0);

    $browser->assertNoJavaScriptErrors();
})->with(['meetup list', 'meetup detail', 'event detail']);

it('binds only values it actually offers as options', function (?string $expected, string $sessionValue) {
    session(['lang_country' => $sessionValue]);

    Livewire::test('language.selector')->assertSet('langCountry', $expected);
})->with([
    // The spelling the issue reported. Same locale, different case — the option exists,
    // so the canonical spelling is selected rather than nothing.
    ['de-DE', 'de-de'],
    ['de-DE', 'de-DE'],
    ['de-AT', 'de-AT'],
    // Allowed, but no `lang/fr.json`: no option can exist for it, so nothing is selected
    // and the placeholder shows. `updatedLangCountry()` already treats an empty selection
    // as "no language chosen", so this cannot trigger a redirect either.
    [null, 'fr-BE'],
    [null, 'ru-RU'],
]);
