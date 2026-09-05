<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;

/*
|--------------------------------------------------------------------------
| Issue #55, item 4 — the picker's data-testid prefix has to be stable
|--------------------------------------------------------------------------
|
| The component used to build its ids from Str::random(8). Two properties have
| to hold at once, and they pull in opposite directions:
|
|  - STABLE across renders, because on /{country}/meetups the picker sits next
|    to `wire:model.live="search"`, so every keystroke re-renders it;
|  - UNIQUE within one page, because meetups/landingpage.blade.php renders the
|    component twice (lines 69 and 122) and a fixed literal would make every
|    selector on that page ambiguous.
|
| A per-request counter satisfies both. The Livewire round trip itself is
| covered in tests/Browser/CalendarStreamPickerTest.php with a real browser;
| this file pins the two properties cheaply, plus the one thing the browser
| test cannot see — that a SECOND request in the same PHP process starts the
| numbering over rather than continuing it.
|
*/

/**
 * @return array<int, string>
 */
function issue55CalendarStreamTestIds(string $html): array
{
    preg_match_all('/data-testid="(calendar-stream-[^"]+)"/', $html, $matches);

    sort($matches[1]);

    return $matches[1];
}

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Bitcoin Meetup Testhausen',
        'visible_on_map' => true,
    ]);
});

it('renders the same calendar-stream test ids on two requests for the meetups index', function () {
    $first = issue55CalendarStreamTestIds(
        $this->get(route('meetups.index', ['country' => 'de']))->assertSuccessful()->getContent()
    );

    $second = issue55CalendarStreamTestIds(
        $this->get(route('meetups.index', ['country' => 'de']))->assertSuccessful()->getContent()
    );

    // Seven ids per instance: trigger, panel, three selects, two copy buttons.
    // Without this the comparison below would pass on two empty arrays.
    expect($first)->toHaveCount(7)
        ->and($second)->toBe($first);
});

it('gives the two picker instances on one meetup landing page different test ids', function () {
    $ids = issue55CalendarStreamTestIds(
        $this->get(route('meetups.landingpage', ['country' => 'de', 'meetup' => $this->meetup]))
            ->assertSuccessful()
            ->getContent()
    );

    // Two instances, seven ids each, none of them shared.
    expect($ids)->toHaveCount(14)
        ->and(array_unique($ids))->toHaveCount(14);

    // `beforeLast('-')` would not do here: `calendar-stream-1-copy-all` would
    // yield `calendar-stream-1-copy` and count as a prefix of its own.
    $prefixes = array_values(array_unique(array_map(
        static fn (string $id): string => preg_replace('/^(calendar-stream-\d+)-.*$/', '$1', $id),
        $ids
    )));

    sort($prefixes);

    expect($prefixes)->toBe(['calendar-stream-1', 'calendar-stream-2']);
});

it('keeps the -trigger suffix the event page scopes its overflow fix to', function () {
    // meetups/landingpage-event.blade.php pins the header's shrinkability with
    // `[&_[data-testid$='-trigger']]:…` overrides (Issue #66). Renaming the
    // suffix here would silently un-fix that column, so it is asserted from
    // the side that would break.
    $html = $this->get(route('meetups.landingpage', ['country' => 'de', 'meetup' => $this->meetup]))
        ->assertSuccessful()
        ->getContent();

    $triggers = array_filter(
        issue55CalendarStreamTestIds($html),
        static fn (string $id): bool => str_ends_with($id, '-trigger')
    );

    expect($triggers)->toHaveCount(2);
});
