<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;

require_once __DIR__.'/../Support/PixelContrast.php';

/*
|--------------------------------------------------------------------------
| Issue #144 — `text-zinc-500` with no `dark:` variant on the dashboard
|--------------------------------------------------------------------------
|
| A bare `text-zinc-500` renders the same grey on both grounds. The light page
| has the headroom for it; the dark page does not. Every muted line on the
| dashboard that carried it now carries `text-zinc-600 dark:text-zinc-300`,
| the pair #123 settled on for the neighbouring sub-lines of the same widgets.
|
| Every probe is measured twice. First as the page ships. Then with the fix
| taken off the live element and `text-zinc-500` put back, which is the
| reported rendering and this file's negative control. The control's result
| is pinned per theme, in both directions: the old class has to FAIL on the
| dark page and PASS on the light one. A control that only expected failure
| would stay green on a page that had stopped being measurable at all.
|
| `text-zinc-500` can be put back as a class because Tailwind still emits it:
| `dashboard/activities.blade.php` keeps it, paired with `dark:text-zinc-400`,
| on the meetup age line. The control asserts the computed colour changed, so
| a purged class would fail loudly instead of measuring the new colour twice.
|
| The three lazy placeholders (activities, top-countries, top-meetups) carry
| the same subtitle markup and were changed identically. They are not probed:
| they exist only until the widget's first round trip, which is not a state a
| screenshot can be pinned to without stubbing Livewire's lazy loader.
|
| Set ISSUE_144_DUMP=<path> to have every reading appended there as JSON.
|
*/

dataset('issue144-viewports', ['narrow' => 375, 'desktop' => 1280]);
dataset('issue144-themes', ['light', 'dark']);

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Kontraststadt',
    ]);
});

function issue144Record(string $probe, string $theme, int $viewport, string $state, array $measured, float $threshold): void
{
    $path = env('ISSUE_144_DUMP');

    if (! $path) {
        return;
    }

    file_put_contents($path, json_encode([
        'probe' => $probe,
        'theme' => $theme,
        'viewport' => $viewport,
        'state' => $state,
        'text' => $measured['css']['text'],
        'fontSize' => $measured['css']['fontSize'],
        'fg' => $measured['gd']['fg'],
        'bg' => $measured['gd']['bg'],
        'ratio' => $measured['gd']['ratio'],
        'threshold' => $threshold,
    ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
}

/**
 * Measures one probe as it ships, then with the reported class restored.
 *
 * `$beforePassedOnLight` states, per probe, whether the old class cleared the
 * bar on the light page. It did on every card ground (4.742:1 on white); it did
 * not inside the open listbox, where the highlighted option sits on a grey
 * ground (4.349:1 on #F5F5F5) — so that line failed in BOTH themes, which the
 * issue did not report.
 *
 * @return array{now: float, before: float, threshold: float}
 */
function issue144MeasureProbe(object $page, string $probe, string $theme, int $viewport, bool $beforePassedOnLight = true): array
{
    $selector = '[data-testid="'.$probe.'"]';

    $now = pixelMeasureText($page, $selector, "issue-144-{$probe}-{$theme}-{$viewport}-now");
    $threshold = pixelTextThreshold($now['css']['fontSize'], $now['css']['fontWeight']);
    issue144Record($probe, $theme, $viewport, 'fixed', $now, $threshold);

    expect([$probe, $theme, $viewport, (float) $now['css']['effectiveOpacity']])
        ->toBe([$probe, $theme, $viewport, 1.0]);
    expect([$probe, $theme, $viewport, $now['gd']['ratio'], $now['gd']['ratio'] >= $threshold])
        ->toBe([$probe, $theme, $viewport, $now['gd']['ratio'], true]);

    // ---- negative control: the rendering issue #144 reported -------------
    $restored = (string) $page->script('(() => {
        const e = document.querySelector('.json_encode($selector).');
        const was = getComputedStyle(e).color;
        e.classList.remove("text-zinc-600", "dark:text-zinc-300");
        e.classList.add("text-zinc-500");
        void e.offsetHeight;
        return was + "|" + getComputedStyle(e).color;
    })()');
    [$colourNow, $colourBefore] = explode('|', $restored);
    expect([$probe, $colourBefore === $colourNow])->toBe([$probe, false]);

    $before = pixelMeasureText($page, $selector, "issue-144-{$probe}-{$theme}-{$viewport}-before");
    issue144Record($probe, $theme, $viewport, 'text-zinc-500', $before, $threshold);

    expect([$probe, $theme, $viewport, $before['gd']['ratio'] >= $threshold])
        ->toBe([$probe, $theme, $viewport, $theme === 'light' && $beforePassedOnLight]);

    // Put the fix back so a later probe on the same page sees the page as shipped.
    $page->script('(() => {
        const e = document.querySelector('.json_encode($selector).');
        e.classList.remove("text-zinc-500");
        e.classList.add("text-zinc-600", "dark:text-zinc-300");
        return true;
    })()');

    return ['now' => $now['gd']['ratio'], 'before' => $before['gd']['ratio'], 'threshold' => $threshold];
}

it('keeps the muted dashboard lines readable when every widget has data', function (int $viewport, string $theme) {
    $user = actingAsUser(['name' => 'Contrast Reader']);

    $mine = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Mein Kontrast Meetup',
        'created_by' => $user->id,
    ]);
    $user->meetups()->syncWithoutDetaching([$mine->id]);

    // Someone else's, so it is not attached and shows up in the "add a meetup"
    // listbox with its city. Created by the acting user it would be attached to
    // them on creation and never reach the listbox.
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Anderes Kontrast Meetup',
        'created_by' => User::factory(),
    ]);

    MeetupEvent::factory()->create([
        'meetup_id' => $mine->id,
        'start' => now()->addWeek(),
        'created_by' => $user->id,
    ]);

    $page = visit('/de/dashboard');
    // Three of the widgets are #[Lazy] and paint a placeholder first.
    $page->resize($viewport, 1400)->wait(3.0);
    pixelSwitchTheme($page, $theme);

    foreach ([
        'dashboard-event-place',
        'dashboard-activities-subtitle',
        'dashboard-activity-event-start',
        'dashboard-top-countries-subtitle',
        'dashboard-top-country-members',
        'dashboard-top-meetups-subtitle',
    ] as $probe) {
        issue144MeasureProbe($page, $probe, $theme, $viewport);
    }

    // The city name sits inside a listbox option, which has no box until the
    // listbox is open — and on a different ground: Flux highlights the first
    // option on open, so the line sits on #F5F5F5 light / #525252 dark.
    // The page carries five Flux listboxes (theme, locale, timezone, country,
    // this one); the options are not in the DOM until one opens, so the button
    // is found by its placeholder.
    $opened = $page->script('(() => {
        const button = [...document.querySelectorAll("[data-flux-select-button]")]
            .find((b) => b.textContent.includes("Meetup hinzufügen"));
        if (! button) { return false; }
        button.click();
        return true;
    })()');
    expect($opened)->toBeTrue();
    $page->wait(0.8);
    issue144MeasureProbe($page, 'dashboard-available-meetup-city', $theme, $viewport, beforePassedOnLight: false);
})->with('issue144-viewports')->with('issue144-themes');

it('keeps the dashboard empty states readable', function (int $viewport, string $theme) {
    actingAsUser(['name' => 'Contrast Reader']);

    $page = visit('/de/dashboard');
    $page->resize($viewport, 1400)->wait(3.0);
    pixelSwitchTheme($page, $theme);

    foreach ([
        'dashboard-no-events',
        'dashboard-no-meetups',
        'dashboard-no-activities',
        'dashboard-no-top-countries',
        'dashboard-no-top-meetups',
    ] as $probe) {
        issue144MeasureProbe($page, $probe, $theme, $viewport);
    }
})->with('issue144-viewports')->with('issue144-themes');
