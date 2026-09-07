<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;

require_once __DIR__.'/../Support/PixelContrast.php';

/*
|--------------------------------------------------------------------------
| Issue #123 — the sites that used to dim text with `opacity-*`
|--------------------------------------------------------------------------
|
| `opacity` multiplies the alpha of the glyph AND of the ground it sits on by
| the same factor, so the ratio between them collapses with it. A colour that
| clears 4.5:1 on its own does not clear it inside an `opacity-60` wrapper, and
| no amount of picking a better token repairs that — the fade has to go.
|
| Every case here is measured twice: once as the page ships, and once with the
| removed `opacity` put straight back onto the live element. The second reading
| is this file's negative control. Without it the first reading would be a
| number with nothing to compare against, and a probe that cannot fail is not
| measuring anything. `pixelForceOpacity()` returns the computed opacity so the
| control proves it applied rather than assuming it.
|
| Why an inline style rather than the class: Tailwind only emits a utility some
| source file still uses, and the point of the fix is that none does any more.
| `opacity-60` and `style.opacity = '0.6'` compute to the same value, which the
| assertion on the return value pins.
|
| WHAT REPLACED THE FADE, per site:
|
|   - The four inactive-meetup sites (meetups/index, dashboard, dashboard/
|     activities, dashboard/top-meetups) carried NO information in the fade.
|     Each already says "Inaktiv" in a badge and renders its logo `grayscale` —
|     a word and a form, neither of them a colour. The fade was a third,
|     weaker copy of a signal already given twice, and the only one of the
|     three that cost legibility. It is gone and nothing replaced it.
|   - The OSM address lines (osm/place-picker, courses/create-edit-events)
|     needed to stay secondary, so the dimming moved from `opacity` onto a
|     named muted colour: `text-zinc-600 dark:text-zinc-300`, the pair the tag
|     picker already uses for its provenance line.
|   - The RSVP-dependent block in meetups/edit said "unavailable" by fading.
|     It now says it in words, under the control, at full contrast.
|
| Set ISSUE_123_DUMP=<path> to have every reading appended there as JSON — the
| way the table in the issue was produced, and the way to reproduce it after a
| Flux or Tailwind bump.
|
*/

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Kontraststadt',
    ]);
});

/** Appends one reading to the dump file, when one was asked for. */
function issue123Record(string $site, string $theme, int $viewport, string $state, array $measured): void
{
    $path = env('ISSUE_123_DUMP');

    if (! $path) {
        return;
    }

    file_put_contents($path, json_encode([
        'site' => $site,
        'theme' => $theme,
        'viewport' => $viewport,
        'state' => $state,
        'text' => $measured['css']['text'],
        'fontSize' => $measured['css']['fontSize'],
        'fontWeight' => $measured['css']['fontWeight'],
        'effectiveOpacity' => $measured['css']['effectiveOpacity'],
        'fg' => $measured['gd']['fg'],
        'bg' => $measured['gd']['bg'],
        'ratio' => $measured['gd']['ratio'],
        'threshold' => pixelTextThreshold($measured['css']['fontSize'], $measured['css']['fontWeight']),
    ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
}

/**
 * Measures every probe of one site as it ships, then puts the removed fade back
 * on the named wrapper and measures them all again.
 *
 * @param  array<string, string>  $probes  label => CSS selector of a text-only element
 * @return array<string, array{now: float, faded: float, threshold: float, fontSize: float}>
 */
function issue123MeasureSite(
    object $page,
    string $site,
    string $theme,
    int $viewport,
    string $wrapper,
    string $fadedOpacity,
    array $probes,
    array $stripClasses = [],
): array {
    $slug = str_replace(['/', ' '], '-', $site);
    $results = [];

    foreach ($probes as $label => $selector) {
        $now = pixelMeasureText($page, $selector, "issue-123-{$slug}-{$theme}-{$viewport}-{$label}-now");
        issue123Record($site, $theme, $viewport, 'fixed', $now);

        $threshold = pixelTextThreshold($now['css']['fontSize'], $now['css']['fontWeight']);

        // Nothing anywhere up the ancestor chain may still be fading this.
        expect([$site, $label, (float) $now['css']['effectiveOpacity']])->toBe([$site, $label, 1.0]);

        expect([$site, $theme, $viewport, $label, $now['gd']['ratio'] >= $threshold])
            ->toBe([$site, $theme, $viewport, $label, true]);

        $results[$label] = [
            'now' => $now['gd']['ratio'],
            'threshold' => $threshold,
            'fontSize' => $now['css']['fontSize'],
            'fg' => $now['gd']['fg'],
            'bg' => $now['gd']['bg'],
        ];
    }

    // ---- negative control ------------------------------------------------
    // Put the site back exactly as it was reported, not approximately: where the
    // fix also renamed a colour, that class comes off again first, or the number
    // below would be the new colour under the old fade — a rendering the page
    // never showed anybody.
    if ($stripClasses !== []) {
        foreach ($probes as $selector) {
            pixelStripClasses($page, $selector, $stripClasses);
        }
    }

    $computed = pixelForceOpacity($page, $wrapper, $fadedOpacity);
    expect([$site, $computed])->toBe([$site, $fadedOpacity]);

    foreach ($probes as $label => $selector) {
        $faded = pixelMeasureText($page, $selector, "issue-123-{$slug}-{$theme}-{$viewport}-{$label}-faded");
        issue123Record($site, $theme, $viewport, 'opacity-'.((int) round((float) $fadedOpacity * 100)), $faded);

        expect([$site, $label, (float) $faded['css']['effectiveOpacity']])->toBe([$site, $label, (float) $fadedOpacity]);

        // The mechanism, and the part of the control that holds everywhere: the
        // fade always costs contrast, because it moves glyph and ground toward
        // each other. If the fix had failed to land, this reading would equal
        // the one above and this line would say so.
        expect([$site, $label, $faded['gd']['ratio'] < $results[$label]['now']])
            ->toBe([$site, $label, true]);

        $results[$label]['faded'] = $faded['gd']['ratio'];
        $results[$label]['fadedFg'] = $faded['gd']['fg'];
        $results[$label]['fadedBg'] = $faded['gd']['bg'];
    }

    pixelClearOpacity($page, $wrapper);

    return $results;
}

/**
 * How many of a site's probes the fade pushes under their own threshold.
 *
 * Every caller states this number per theme rather than expecting "all of
 * them", because measuring it here refuted the premise issue #123 carried over
 * from #114 — "every colour that passes on its own fails inside an `opacity-*`
 * wrapper". It does not. What `opacity` costs is a FIXED FRACTION of the
 * headroom, so whether a pair survives depends on how much headroom it had.
 * Measured on the light page of this build at alpha 0.6:
 *
 *     21.000:1  (#000000 body text)   ->  5.829:1   still passes
 *     15.134:1  (zinc-800 link)       ->  4.174:1   fails
 *      7.814:1  (zinc-600 sub-line)   ->  2.958:1   fails
 *      4.742:1  (zinc-500 tertiary)   ->  2.296:1   fails
 *
 * — break-even somewhere near 17:1, i.e. only pure black on pure white has the
 * reserve to absorb it. The dark page is gentler still (white on #262626 keeps
 * 6.364:1), because fading a light glyph toward a dark ground closes the gap
 * more slowly than fading a dark glyph toward a light one.
 *
 * None of that makes the fade acceptable: it spends between a third and three
 * quarters of every pair's reserve to say something the badge beside it already
 * says in words. It does mean the honest claim is "the fade always costs
 * contrast, and takes the muted material under the line", not "everything
 * fails".
 *
 * @param  array<string, array{faded: float, threshold: float}>  $measured
 */
function issue123CountBelowThreshold(array $measured): int
{
    return count(array_filter($measured, fn (array $r): bool => $r['faded'] < $r['threshold']));
}

/**
 * Seeds one inactive meetup, attached to the acting user so it reaches all
 * three dashboard widgets as well as the public list.
 */
function issue123SeedInactiveMeetup(object $test): Meetup
{
    $user = actingAsUser(['name' => 'Contrast Reader']);

    $meetup = Meetup::factory()->inactive()->create([
        'city_id' => $test->city->id,
        'name' => 'Inaktives Kontrast Meetup',
        'slug' => 'inaktives-kontrast-meetup',
        'created_by' => $user->id,
    ]);

    $user->meetups()->syncWithoutDetaching([$meetup->id]);

    return $meetup;
}

/*
|--------------------------------------------------------------------------
| meetups/index — the whole table row was faded
|--------------------------------------------------------------------------
*/

dataset('viewports', [
    'narrow' => 375,
    'desktop' => 1280,
]);

dataset('themes', ['light', 'dark']);

it('keeps an inactive meetup row readable on the meetup list', function (int $viewport, string $theme) {
    issue123SeedInactiveMeetup($this);

    $page = visit('/de/meetups');
    $page->resize($viewport, 900)->wait(1.5);
    pixelSwitchTheme($page, $theme);

    $row = '[data-testid="meetup-row"][data-active="false"]';

    $measured = issue123MeasureSite($page, 'meetups-index', $theme, $viewport, $row, '0.6', [
        'name' => $row.' td:nth-child(1) a[aria-label]',
        'place' => $row.' td:nth-child(1) div.text-xs.text-zinc-600',
        'lastevent' => $row.' td:nth-child(2) span.text-xs',
        // The badge is the whole reason the fade could go: it is the row's only
        // statement that the meetup is inactive, and the fade was dimming it too.
        'badge' => $row.' td:nth-child(2) [data-flux-badge]',
    ]);

    // With the fade back on, the light page loses all four probes. The dark page
    // loses the badge — the row's only statement that the meetup is inactive,
    // and the one thing in the row that had no headroom to give.
    expect([$theme, issue123CountBelowThreshold($measured)])
        ->toBe([$theme, $theme === 'light' ? 4 : 1]);

    expect([$theme, 'badge', $measured['badge']['faded'] < 4.5])->toBe([$theme, 'badge', true]);
})->with('viewports')->with('themes');

/*
|--------------------------------------------------------------------------
| The three dashboard widgets — one page, three separately faded blocks
|--------------------------------------------------------------------------
*/

it('keeps the inactive meetup readable in all three dashboard widgets', function (int $viewport, string $theme) {
    issue123SeedInactiveMeetup($this);

    $page = visit('/de/dashboard');
    // Two of the three widgets are #[Lazy]: they paint a placeholder first.
    $page->resize($viewport, 1400)->wait(3.0);
    pixelSwitchTheme($page, $theme);

    $mine = '[data-testid="dashboard-my-meetup"][data-active="false"]';
    $activity = '[data-testid="dashboard-activity-meetup"][data-active="false"]';
    $top = '[data-testid="dashboard-top-meetup"][data-active="false"]';

    // `:not([data-flux-badge])` keeps the name probe off the "Inaktiv" badge,
    // which carries `font-medium` of its own; scoping to the anchor keeps it off
    // the edit/remove buttons and the removal modal further down the wrapper.
    // The last element is how many probes the fade takes under their threshold,
    // per theme — measured, not assumed. The names survive it in both themes
    // (they start at 21:1); what crosses is the muted material underneath.
    $sites = [
        ['dashboard-my-meetups', $mine, [
            'name' => $mine.' a div.font-medium:not([data-flux-badge])',
            'place' => $mine.' a div.text-xs.text-zinc-600',
        ], ['light' => 1, 'dark' => 0]],
        ['dashboard-activities', $activity, [
            'name' => $activity.' div.font-medium:not([data-flux-badge])',
            'place' => $activity.' div.text-xs.text-zinc-600',
            'age' => $activity.' div.text-xs.text-zinc-500',
        ], ['light' => 2, 'dark' => 1]],
        ['dashboard-top-meetups', $top, [
            'name' => $top.' div.font-medium > span',
            'members' => $top.' div.text-xs.text-zinc-600',
        ], ['light' => 1, 'dark' => 0]],
    ];

    foreach ($sites as [$site, $wrapper, $probes, $crossings]) {
        $measured = issue123MeasureSite($page, $site, $theme, $viewport, $wrapper, '0.6', $probes);

        // Pinned in both directions. A control that only ever expects failure
        // goes green on a page that has quietly stopped being measurable at all;
        // one that expects failure everywhere would have to be loosened the
        // first time it met text with enough reserve to survive the fade, and
        // loosening is how a control stops controlling anything.
        expect([$site, $theme, issue123CountBelowThreshold($measured)])
            ->toBe([$site, $theme, $crossings[$theme]]);
    }
})->with('viewports')->with('themes');

/*
|--------------------------------------------------------------------------
| osm/place-picker — the two lines under a chosen place
|--------------------------------------------------------------------------
|
| Reached through the city form rather than an event form: the picker is the
| same component in both (cities/create, cities/edit, and the two event forms
| all mount `livewire:osm.place-picker`), and the city form needs no meetup, no
| event and no leadership to render it.
*/

it('keeps the two lines under a chosen OSM place readable', function (int $viewport, string $theme) {
    actingAsUser();

    $city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Ortsstadt',
        'osm_type' => 'node',
        'osm_id' => 240109189,
        'osm_name' => 'Ortsstadt',
        'osm_address' => 'Ortsstadt, Regierungsbezirk Mittelfranken, Bayern, Deutschland',
        'osm_lat' => 49.4521,
        'osm_lon' => 11.0767,
    ]);

    $page = visit('/de/city-edit/'.$city->id);
    $page->resize($viewport, 1200)->wait(2.0);
    pixelSwitchTheme($page, $theme);

    $box = '[data-testid="osm-chosen"]';

    $measured = issue123MeasureSite($page, 'place-picker-chosen', $theme, $viewport, $box, '0.6', [
        'address' => $box.' div.mt-0\\.5',
        'osmid' => $box.' div.mt-1',
    ], ['text-zinc-600', 'dark:text-zinc-300']);

    // Reported rendering restored, and it refutes the issue's "worst case"
    // label for these two lines: 5.742:1 light, 6.364:1 dark. Both clear 4.5:1.
    // 12px is not what decides this — WCAG 1.4.3 only ever moves the bar DOWN,
    // for text at 24px or 18.66px bold, and never up for small text. What
    // decides it is the pair the fade starts from, and these two lines start
    // from plain black on white (21:1), which has the reserve to absorb 40%.
    //
    // The class came off anyway. The fade was spending three quarters of that
    // reserve to say "secondary", which a named colour says for nothing, and it
    // left the two lines one token change away from failing silently.
    expect([$theme, issue123CountBelowThreshold($measured)])->toBe([$theme, 0]);
})->with('viewports')->with('themes');

/*
|--------------------------------------------------------------------------
| osm/place-picker and courses/create-edit-events — the search-result lines
|--------------------------------------------------------------------------
|
| `$results` / `$newCityResults` are plain public arrays, so the state is set
| through Livewire from the browser and the SERVER re-renders the real Blade.
| That is the point: the alternative is a live Nominatim call, which would make
| a contrast test depend on a third party's uptime and rate limit, and injecting
| the markup by hand would measure markup this file wrote rather than markup the
| component wrote.
*/

function issue123SeedPickerResults(object $page, string $component, string $property): void
{
    $page->script('(() => {
      const c = Livewire.getByName('.json_encode($component).')[0];
      c.set('.json_encode($property).', [{
        osm_type: "node", osm_id: 240109189,
        osm_name: "Bitcoin Bar",
        osm_address: "Hauptstrasse 1, Mittelfranken, Bayern, Deutschland",
        osm_lat: 49.4521, osm_lon: 11.0767,
      }]);
      return true;
    })()');
    $page->wait(1.2);
}

it('keeps the OSM search-result address line readable', function (int $viewport, string $theme) {
    actingAsUser();

    $page = visit('/de/city-create');
    $page->resize($viewport, 1200)->wait(2.0);
    issue123SeedPickerResults($page, 'osm.place-picker', 'results');
    pixelSwitchTheme($page, $theme);

    $hit = '[data-testid="osm-result-0"]';

    $measured = issue123MeasureSite($page, 'place-picker-result', $theme, $viewport, $hit, '0.6', [
        'address' => $hit.' div.text-xs',
    ], ['text-zinc-600', 'dark:text-zinc-300']);

    // Measured, and it refutes one of the fourteen sites the issue listed: this
    // line never violated 1.4.3. The button it sits in inherits the page's plain
    // black, and black at 60% over white is 5.742:1 — the only pair on the list
    // with enough reserve to absorb the fade. The class went anyway, because the
    // two lines directly above it in the same component DID fail and a picker
    // that dims one of its three secondary lines by a different mechanism than
    // the other two is a coin toss for whoever edits it next.
    expect([$theme, issue123CountBelowThreshold($measured)])->toBe([$theme, 0]);
})->with('viewports')->with('themes');

it('keeps the add-a-city search-result address line readable in the course event form', function (int $viewport, string $theme) {
    $user = actingAsUser();
    $course = \App\Models\Course::factory()->create(['created_by' => $user->id]);

    $page = visit('/de/course/'.$course->id.'/events/create');
    $page->resize($viewport, 1200)->wait(2.0);

    // The block lives inside the "add a city" modal, which has to be open for
    // the element to have a box to photograph.
    $page->script('(() => { Flux.modal("add-city").show(); return true; })()');
    $page->wait(0.8);
    issue123SeedPickerResults($page, 'courses.create-edit-events', 'newCityResults');
    pixelSwitchTheme($page, $theme);

    $hit = '[data-testid="add-city-result-0"]';

    $measured = issue123MeasureSite($page, 'courses-add-city-result', $theme, $viewport, $hit, '0.6', [
        'address' => $hit.' div.text-xs',
    ], ['text-zinc-600', 'dark:text-zinc-300']);

    // Same reading as the picker's own result line, on a different page and
    // inside an open modal: 5.742:1 light, 6.364:1 dark. No violation here
    // either, and the class went for the same reason.
    expect([$theme, issue123CountBelowThreshold($measured)])->toBe([$theme, 0]);
})->with('viewports')->with('themes');

/*
|--------------------------------------------------------------------------
| meetups/edit — the block that depended on the RSVP switch
|--------------------------------------------------------------------------
*/

it('keeps the RSVP-dependent block readable while it is unavailable', function (int $viewport, string $theme) {
    $meetup = issue123SeedInactiveMeetup($this);
    // The column defaults to 1, so the state under test has to be asked for.
    $meetup->forceFill(['rsvp_enabled' => false])->save();

    $page = visit('/de/meetup-edit/'.$meetup->id);
    $page->resize($viewport, 1400)->wait(2.5);
    pixelSwitchTheme($page, $theme);

    $block = '[data-testid="attendees-public-block"]';

    // The switch is off, so the block is in the state that used to be faded.
    $state = $page->script('(() => {
      const b = document.querySelector('.json_encode($block).');
      const hint = b.querySelector("[data-testid=\'attendees-public-hint\']");
      return {
        hintVisible: !! (hint && hint.offsetParent !== null),
        switchDisabled: b.querySelector("[data-flux-switch]").hasAttribute("disabled"),
        pointerEvents: getComputedStyle(b).pointerEvents,
      };
    })()');

    expect($state['hintVisible'])->toBeTrue()
        ->and($state['switchDisabled'])->toBeTrue()
        // `pointer-events-none` went with the fade. It was redundant beside a
        // genuinely disabled control and additionally killed hover and text
        // selection on the explanation the user needs in order to act.
        ->and($state['pointerEvents'])->toBe('auto');

    $measured = issue123MeasureSite($page, 'meetups-edit-rsvp', $theme, $viewport, $block, '0.5', [
        'hint' => $block.' [data-testid="attendees-public-hint"]',
    ]);

    expect([$theme, issue123CountBelowThreshold($measured)])->toBe([$theme, 1]);
})->with('viewports')->with('themes');

/*
|--------------------------------------------------------------------------
| The three `opacity-90` duplicate-candidate lists — measured, then decided
|--------------------------------------------------------------------------
|
| Issue #123 called these "mild" and asked for a measurement before a decision,
| which is the right order: mild is a description of the class, not of the
| rendering. The list is driven the way a user drives it — a name that already
| exists in the country, submitted — so the server builds the candidates and
| renders the real block.
*/

/** @return array<string, array{now: float, faded: float, threshold: float}> */
function issue123MeasureCandidateList(object $page, string $site, string $theme, int $viewport, string $box): array
{
    $list = $box.' ul';
    $results = [];

    // As it ships: the list is still faded, so this reading IS the "before".
    $faded = pixelMeasureText($page, $list.' li', "issue-123-{$site}-{$theme}-{$viewport}-li-faded");
    issue123Record($site, $theme, $viewport, 'opacity-90', $faded);

    expect([$site, (float) $faded['css']['effectiveOpacity']])->toBe([$site, 0.9]);

    // And with the fade lifted, so the cost of the class is a number and not an
    // adjective. This is the control in the other direction: if `opacity-90`
    // were doing nothing at all, these two readings would be identical.
    pixelForceOpacity($page, $list, '1');
    $full = pixelMeasureText($page, $list.' li', "issue-123-{$site}-{$theme}-{$viewport}-li-full");
    issue123Record($site, $theme, $viewport, 'no-opacity', $full);

    $threshold = pixelTextThreshold($faded['css']['fontSize'], $faded['css']['fontWeight']);

    $results['li'] = [
        'now' => $faded['gd']['ratio'],
        'faded' => $faded['gd']['ratio'],
        'full' => $full['gd']['ratio'],
        'threshold' => $threshold,
    ];

    return $results;
}

it('leaves the opacity-90 duplicate lists alone, and says with what number', function (int $viewport, string $theme) {
    $user = actingAsUser();
    $existing = City::factory()->create(['country_id' => $this->country->id, 'name' => 'Neuenkirchen']);
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $user->id]);
    $countryId = $this->country->id;

    $pages = [
        // site, url, livewire component, the JS that produces the candidates
        ['cities-create-duplicates', '/de/city-create', 'cities.create', <<<JS
        (() => {
          const c = Livewire.getByName('cities.create')[0];
          c.set('country_id', {$countryId});
          c.set('name', 'Neuenkirchen');
          c.call('createCity');
          return true;
        })()
        JS],
        ['meetups-create-duplicates', '/de/meetup-create', 'meetups.create', <<<JS
        (() => {
          Flux.modal('add-city').show();
          const c = Livewire.getByName('meetups.create')[0];
          c.set('newCityCountryId', {$countryId});
          c.set('newCityName', 'Neuenkirchen');
          c.call('createCity');
          return true;
        })()
        JS],
        ['meetups-edit-duplicates', '/de/meetup-edit/'.$meetup->id, 'meetups.edit', <<<JS
        (() => {
          Flux.modal('add-city').show();
          const c = Livewire.getByName('meetups.edit')[0];
          c.set('newCityCountryId', {$countryId});
          c.set('newCityName', 'Neuenkirchen');
          c.call('createCity');
          return true;
        })()
        JS],
    ];

    foreach ($pages as [$site, $url, $component, $trigger]) {
        $page = visit($url);
        $page->resize($viewport, 1400)->wait(2.0);
        $page->script($trigger);
        $page->wait(1.5);
        pixelSwitchTheme($page, $theme);

        $box = '.border-amber-500\\/40';
        $measured = issue123MeasureCandidateList($page, $site, $theme, $viewport, $box);

        // The verdict, with its number attached. Measured: 16.284:1 / 16.442:1 on
        // the light page, 10.603:1 on the dark one, against a 4.5:1 bar. The
        // class stays, and this is the record of why rather than a comment
        // claiming it. The floor is set at 10 so that a future retune of the
        // amber callout's own colours has to come back through here.
        expect([$site, $theme, $measured['li']['faded'] >= $measured['li']['threshold']])
            ->toBe([$site, $theme, true]);
        expect([$site, $theme, $measured['li']['faded'] >= 10.0])
            ->toBe([$site, $theme, true]);

        // But it is not free, and the second reading is what says so.
        expect([$site, $measured['li']['full'] > $measured['li']['faded']])->toBe([$site, true]);
    }
})->with('viewports')->with('themes');
