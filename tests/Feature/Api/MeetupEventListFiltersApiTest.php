<?php

/*
|--------------------------------------------------------------------------
| Issue #163 — filter the public event list by meetup, country and date
|--------------------------------------------------------------------------
|
| Every filter is asserted against an event it must KEEP and one it must DROP:
| a filter that silently does nothing would still return the kept event, so a
| presence check alone proves nothing.
|
*/

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-18 12:00:00');

    $czechia = Country::factory()->create(['code' => 'cz']);
    $germany = Country::factory()->create(['code' => 'de']);

    $this->prague = Meetup::factory()->create(['city_id' => City::factory()->create(['country_id' => $czechia->id])->id]);
    $this->brno = Meetup::factory()->create(['city_id' => City::factory()->create(['country_id' => $czechia->id])->id]);
    $this->berlin = Meetup::factory()->create(['city_id' => City::factory()->create(['country_id' => $germany->id])->id]);
});

afterEach(fn () => Carbon::setTestNow());

function filteredEventIds(string $query): array
{
    return collect(test()->getJson('/api/meetup-events'.$query)->assertOk()->json())
        ->pluck('id')
        ->sort()
        ->values()
        ->all();
}

function eventAt(Meetup $meetup, string $start, ?string $end = null): MeetupEvent
{
    return MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => $start, 'end' => $end]);
}

it('starts the bare list seven days back when no date range is given', function () {
    eventAt($this->prague, '2022-01-10 18:00:00');
    eventAt($this->prague, '2026-10-10 18:00:00', '2026-10-10 22:00:00');
    $endedSevenDaysAgo = eventAt($this->prague, '2026-10-11 18:00:00', '2026-10-11 22:00:00');
    $noEndSixDaysAgo = eventAt($this->prague, '2026-10-12 18:00:00');
    $future = eventAt($this->berlin, '2034-01-10 18:00:00');

    expect(filteredEventIds(''))->toBe([$endedSevenDaysAgo->id, $noEndSixDaysAgo->id, $future->id])
        // The other filters narrow inside the window; they do not lift it.
        ->and(filteredEventIds("?meetup_id={$this->prague->id}"))->toBe([$endedSevenDaysAgo->id, $noEndSixDaysAgo->id]);
});

it('reaches the history through an explicit from or to', function () {
    $old = eventAt($this->prague, '2022-01-10 18:00:00');
    $future = eventAt($this->berlin, '2034-01-10 18:00:00');

    expect(filteredEventIds('?from=2000-01-01'))->toBe([$old->id, $future->id])
        ->and(filteredEventIds('?to=2022-12-31'))->toBe([$old->id]);
});

it('keeps the full month on the dated path, past or not', function () {
    $old = eventAt($this->prague, '2022-01-10 18:00:00');

    $ids = collect($this->getJson('/api/meetup-events/2022-01-01')->assertOk()->json())->pluck('id')->all();

    expect($ids)->toBe([$old->id]);
});

it('filters by meetup, as a repeated or comma separated parameter', function () {
    $prague = eventAt($this->prague, '2026-11-01 18:00:00');
    $brno = eventAt($this->brno, '2026-11-02 18:00:00');
    eventAt($this->berlin, '2026-11-03 18:00:00');

    expect(filteredEventIds("?meetup_id[]={$this->prague->id}&meetup_id[]={$this->brno->id}"))->toBe([$prague->id, $brno->id])
        ->and(filteredEventIds("?meetup_id={$this->prague->id},{$this->brno->id}"))->toBe([$prague->id, $brno->id])
        ->and(filteredEventIds("?meetup_id={$this->prague->id}"))->toBe([$prague->id]);
});

it('filters by country, case-insensitively', function () {
    $prague = eventAt($this->prague, '2026-11-01 18:00:00');
    $brno = eventAt($this->brno, '2026-11-02 18:00:00');
    eventAt($this->berlin, '2026-11-03 18:00:00');

    expect(filteredEventIds('?country=cz'))->toBe([$prague->id, $brno->id])
        ->and(filteredEventIds('?country=CZ'))->toBe([$prague->id, $brno->id])
        ->and(filteredEventIds('?country=at'))->toBe([]);
});

it('keeps an event still running on the from day and drops one that ended before it', function () {
    $endedYesterday = eventAt($this->prague, '2026-10-17 18:00:00', '2026-10-17 22:00:00');
    $startedYesterdayEndsToday = eventAt($this->prague, '2026-10-17 18:00:00', '2026-10-18 02:00:00');
    $noEndToday = eventAt($this->prague, '2026-10-18 00:00:00');
    $noEndYesterday = eventAt($this->prague, '2026-10-17 23:59:00');
    $nextMonth = eventAt($this->prague, '2026-11-20 18:00:00');

    $upcoming = [$startedYesterdayEndsToday->id, $noEndToday->id, $nextMonth->id];

    expect(filteredEventIds('?from=2026-10-18'))->toBe($upcoming)
        ->and(filteredEventIds('?from=today'))->toBe($upcoming)
        ->and(filteredEventIds('?upcoming=1'))->toBe($upcoming)
        ->and(filteredEventIds('?from=2026-10-18'))->not->toContain($endedYesterday->id)
        ->and(filteredEventIds('?from=2026-10-18'))->not->toContain($noEndYesterday->id);
});

it('keeps events starting on the to day and drops later ones', function () {
    $onToDay = eventAt($this->prague, '2026-10-31 23:59:59');
    eventAt($this->prague, '2026-11-01 00:00:00');

    expect(filteredEventIds('?to=2026-10-31'))->toBe([$onToDay->id]);
});

it('combines all filters with AND', function () {
    $match = eventAt($this->prague, '2026-11-01 18:00:00');
    eventAt($this->prague, '2026-09-01 18:00:00');
    eventAt($this->prague, '2027-02-01 18:00:00');
    eventAt($this->brno, '2026-11-01 18:00:00');
    eventAt($this->berlin, '2026-11-01 18:00:00');

    expect(filteredEventIds("?meetup_id={$this->prague->id}&country=cz&from=today&to=2026-12-31"))->toBe([$match->id]);
});

it('applies the filters on the dated path too', function () {
    $prague = eventAt($this->prague, '2026-11-01 18:00:00');
    eventAt($this->berlin, '2026-11-03 18:00:00');

    $ids = collect($this->getJson('/api/meetup-events/2026-11-01?country=cz')->assertOk()->json())->pluck('id')->all();

    expect($ids)->toBe([$prague->id]);
});

it('includes the meetup id on every row', function () {
    eventAt($this->prague, '2026-11-01 18:00:00');

    $row = $this->getJson('/api/meetup-events')->assertOk()->json(0);

    expect($row['meetup_id'])->toBe($this->prague->id)
        ->and($row['meetup.id'])->toBe($this->prague->id);
});

it('rejects an invalid or unknown filter with a JSON 422', function (string $query, string $errorKey) {
    // get(), not getJson(): a consumer without an Accept header must get the JSON
    // error too, not the redirect Laravel answers a non-JSON request with.
    $this->get('/api/meetup-events'.$query)
        ->assertStatus(422)
        ->assertJsonValidationErrors($errorKey);
})->with([
    'unknown parameter' => ['?meetupId=1', 'meetupId'],
    'non-numeric meetup id' => ['?meetup_id=abc', 'meetup_id.0'],
    'three-letter country' => ['?country=cze', 'country'],
    'malformed from' => ['?from=18.10.2026', 'from'],
    'to before from' => ['?from=2026-10-18&to=2026-10-01', 'to'],
    'upcoming with from' => ['?upcoming=1&from=2026-10-18', 'upcoming'],
]);

it('still accepts locale next to the filters', function () {
    eventAt($this->prague, '2026-11-01 18:00:00');

    $this->get('/api/meetup-events?locale=cs&country=cz')->assertOk()->assertJsonCount(1);
});
