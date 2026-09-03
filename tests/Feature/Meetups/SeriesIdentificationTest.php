<?php

use App\Enums\RecurrenceType;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Events of a recurring series must be identifiable outside the edit form (#43).
 *
 * Until now the only marker lived in `create-edit-events.blade.php`
 * (`data-testid="series-locked-note"`, pinned by EditRecurringEventControlsTest) —
 * visible to a leader in edit mode and to nobody else. These tests pin the two public
 * markers:
 *
 *  - `data-testid="series-badge"` on every card of the "Kommende Veranstaltungen" grid
 *    in `meetups/landingpage.blade.php` whose event belongs to a series
 *  - `data-testid="series-note"` on the single-event page
 *    `meetups/landingpage-event.blade.php` when the shown event belongs to a series
 *
 * The gate is `$event->recurrence_group !== null` and NOTHING ELSE. `recurrence_type`
 * is the wrong column, in both directions:
 *
 *  - 2026_08_25_194948_group_existing_meetup_event_series backfilled `recurrence_group`
 *    only and set no `recurrence_*` rule column, so every pre-P5 series carries
 *    `recurrence_type = null`. A gate on the rule would leave exactly the series that
 *    #43 was filed about unmarked.
 *  - The reverse state is reachable too: `POST /api/meetup-events` and `PATCH` have
 *    always accepted `recurrence_type` on a single event without a group (see the
 *    third branch of Meetup::recalculateActivity()). A gate on the rule would mark a
 *    lone event as a series.
 *
 * Both directions have their own test below; together they fail whenever someone keys
 * the gate off `recurrence_type`.
 *
 * The assertions deliberately look at the `data-testid` attributes, never at the German
 * copy — the wording belongs to the markup author and may change without changing the
 * behaviour this file guards.
 */

/**
 * A meetup that renders on the public landing page. Ownership is irrelevant here: the
 * markers are for visitors, so every case below runs as a guest.
 */
function meetupForSeriesIdentification(): Meetup
{
    return Meetup::factory()->create();
}

/**
 * An occurrence of a series in its post-P5 shape: group AND rule.
 * `start` is pinned into the future because the card grid only lists
 * `start >= now()`.
 */
function seriesOccurrence(Meetup $meetup): MeetupEvent
{
    return MeetupEvent::factory()->for($meetup)->create([
        'start' => now()->addWeek(),
        'recurrence_type' => RecurrenceType::Weekly->value,
        'recurrence_end_date' => now()->addYear(),
        'recurrence_group' => (string) Str::uuid(),
    ]);
}

/**
 * The pre-backfill shape: `recurrence_group` set by the grouping migration, no rule
 * column at all. This is the fixture that separates the two columns — a gate on
 * `recurrence_type` renders nothing for it.
 */
function backfilledSeriesOccurrence(Meetup $meetup): MeetupEvent
{
    return MeetupEvent::factory()->for($meetup)->create([
        'start' => now()->addWeek(),
        'recurrence_type' => null,
        'recurrence_day_of_week' => null,
        'recurrence_day_position' => null,
        'recurrence_end_date' => null,
        'recurrence_group' => (string) Str::uuid(),
    ]);
}

/**
 * A single event, no series in any sense. The factory sets `recurrence_type` on roughly
 * 40 % of its rows, so both columns are pinned explicitly — otherwise this fixture would
 * randomly turn into the "rule without group" case below and the test would only
 * sometimes measure what it claims to.
 */
function standaloneEvent(Meetup $meetup): MeetupEvent
{
    return MeetupEvent::factory()->for($meetup)->create([
        'start' => now()->addWeek(),
        'recurrence_type' => null,
        'recurrence_end_date' => null,
        'recurrence_group' => null,
    ]);
}

/**
 * A single event carrying a recurrence rule but no group — writable through the REST
 * single-event path since forever. It is NOT a series: nothing ties it to a second
 * event.
 */
function ruleWithoutGroupEvent(Meetup $meetup): MeetupEvent
{
    return MeetupEvent::factory()->for($meetup)->create([
        'start' => now()->addWeek(),
        'recurrence_type' => RecurrenceType::Weekly->value,
        'recurrence_end_date' => now()->addYear(),
        'recurrence_group' => null,
    ]);
}

it('marks the card of an event that belongs to a series', function () {
    $meetup = meetupForSeriesIdentification();
    seriesOccurrence($meetup);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertStatus(200)
        ->assertSeeHtml('data-testid="series-badge"');
});

it('marks the card of a pre-backfill series event that carries no recurrence type', function () {
    $meetup = meetupForSeriesIdentification();
    $event = backfilledSeriesOccurrence($meetup);

    // The whole point of #43: this row is a series and has been one since long before
    // any `recurrence_*` column was written.
    expect($event->recurrence_type)->toBeNull()
        ->and($event->recurrence_group)->not->toBeNull();

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertStatus(200)
        ->assertSeeHtml('data-testid="series-badge"');
});

it('leaves the card of a standalone event unmarked', function () {
    $meetup = meetupForSeriesIdentification();
    standaloneEvent($meetup);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertStatus(200)
        ->assertDontSeeHtml('data-testid="series-badge"');
});

it('leaves the card unmarked for an event that carries a recurrence rule but no group', function () {
    $meetup = meetupForSeriesIdentification();
    ruleWithoutGroupEvent($meetup);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertStatus(200)
        ->assertDontSeeHtml('data-testid="series-badge"');
});

it('marks only the series cards when the list mixes a series with single events', function () {
    $meetup = meetupForSeriesIdentification();

    // Two occurrences of one series, plus two events that must stay unmarked. Counting
    // instead of asserting mere presence: a marker rendered unconditionally on every
    // card would satisfy assertSeeHtml just as well.
    $group = (string) Str::uuid();
    MeetupEvent::factory()->count(2)->for($meetup)->series($group, start: now()->addWeek())->create();
    standaloneEvent($meetup);
    ruleWithoutGroupEvent($meetup);

    $html = Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertStatus(200)
        ->html();

    expect(substr_count($html, 'data-testid="series-badge"'))->toBe(2);
});

it('marks the detail page of an event that belongs to a series', function () {
    $meetup = meetupForSeriesIdentification();
    $event = seriesOccurrence($meetup);

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertStatus(200)
        ->assertSeeHtml('data-testid="series-note"');
});

it('marks the detail page of a pre-backfill series event that carries no recurrence type', function () {
    $meetup = meetupForSeriesIdentification();
    $event = backfilledSeriesOccurrence($meetup);

    expect($event->recurrence_type)->toBeNull()
        ->and($event->recurrence_group)->not->toBeNull();

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertStatus(200)
        ->assertSeeHtml('data-testid="series-note"');
});

it('leaves the detail page of a standalone event unmarked', function () {
    $meetup = meetupForSeriesIdentification();
    $event = standaloneEvent($meetup);

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertStatus(200)
        ->assertDontSeeHtml('data-testid="series-note"');
});

it('leaves the detail page unmarked for an event that carries a recurrence rule but no group', function () {
    $meetup = meetupForSeriesIdentification();
    $event = ruleWithoutGroupEvent($meetup);

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertStatus(200)
        ->assertDontSeeHtml('data-testid="series-note"');
});
