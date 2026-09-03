<?php

use App\Enums\RecurrenceType;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Recurrence is a CREATION-only setting (#43).
 *
 * save() has always branched on `$this->seriesMode && !$this->event`, so editing an
 * occurrence never wrote a single `recurrence_*` column and never created a second
 * event. Only the form claimed otherwise: mount() flips $seriesMode on for any stored
 * rule, which used to reveal the series fields, the "Serientermine erstellen" button
 * and its mass-creation modal in edit mode as well.
 *
 * The German literals below are the load-bearing part of the assertions. Each one
 * identifies exactly one block of the form:
 *  - 'Aktiviere diese Option, …'                -> the series switch
 *  - 'Datum des letzten Termins'                -> the series field block
 *  - 'Serientermine erstellen'                  -> the series button (and modal title)
 *  - 'Du bist dabei, mehrere Events zu erstellen.' -> the confirmation modal
 *  - 'Vorschau der Termine'                     -> the series preview card
 */

/**
 * A meetup whose creator is the acting user — managing events requires leadership,
 * and the creator becomes leader through a model hook.
 */
function meetupWithActingUserAsLeader(): Meetup
{
    return Meetup::factory()->create(['created_by' => actingAsUser()->id]);
}

it('shows the update button and no series controls when editing an event that carries a recurrence rule', function () {
    $meetup = meetupWithActingUserAsLeader();

    $event = MeetupEvent::factory()->for($meetup)->create([
        'recurrence_type' => RecurrenceType::Weekly->value,
        'recurrence_end_date' => now()->addYear(),
        'recurrence_group' => (string) Str::uuid(),
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        // The stored rule is still loaded — the gate hides the UI, not the flag.
        ->assertSet('seriesMode', true)
        ->assertSee(__('Event aktualisieren'))
        ->assertDontSee(__('Serientermine erstellen'))
        ->assertDontSee(__('Du bist dabei, mehrere Events zu erstellen.'))
        ->assertDontSee(__('Datum des letzten Termins'))
        ->assertDontSee(__('Wiederholungstyp'))
        ->assertDontSee(__('Aktiviere diese Option, um mehrere Events mit regelmäßigen Abständen zu erstellen'))
        ->assertDontSee(__('Vorschau der Termine'))
        // The date field describes THIS occurrence, not the start of a series that is
        // being laid out — editing occurrence five is not editing the first one.
        ->assertSee(__('An welchem Tag findet das Event statt?'))
        ->assertDontSee(__('Datum des ersten Termins'));
});

it('saves a series occurrence whose series end date is null', function () {
    $meetup = meetupWithActingUserAsLeader();

    // Nullable per 2026_01_17_163021_add_recurrence_rules_to_meetup_events: an
    // occurrence can carry a rule without an end. mount() then leaves endDate as '',
    // and the required rule used to block every save — a description fix included.
    $event = MeetupEvent::factory()->for($meetup)->create([
        'recurrence_type' => RecurrenceType::Weekly->value,
        'recurrence_end_date' => null,
        'recurrence_group' => (string) Str::uuid(),
        'location' => 'Marktplatz',
        'description' => 'Alter Text',
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSet('endDate', '')
        ->set('description', 'Korrigierte Beschreibung')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $event->refresh();

    expect($event->description)->toBe('Korrigierte Beschreibung')
        // The stored rule survives an edit untouched, and nothing was mass-created.
        ->and($event->recurrence_type)->toBe(RecurrenceType::Weekly)
        ->and($event->recurrence_end_date)->toBeNull()
        ->and(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(1);
});

it('notes that series settings are fixed when editing an event that belongs to a series', function () {
    $meetup = meetupWithActingUserAsLeader();

    // The pre-P5 shape: no write path filled `recurrence_*`, and
    // 2026_08_25_194948_group_existing_meetup_event_series backfilled `recurrence_group`
    // only. It is therefore the sole marker that also identifies an older series.
    $event = MeetupEvent::factory()->for($meetup)->create([
        'recurrence_type' => null,
        'recurrence_end_date' => null,
        'recurrence_group' => (string) Str::uuid(),
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSet('seriesMode', false)
        ->assertSeeHtml('data-testid="series-locked-note"')
        ->assertSee(__('Dieses Event gehört zu einer Serie'));
});

it('shows no series note when editing a standalone event', function () {
    $meetup = meetupWithActingUserAsLeader();

    $event = MeetupEvent::factory()->for($meetup)->create([
        'recurrence_type' => null,
        'recurrence_group' => null,
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertDontSeeHtml('data-testid="series-locked-note"');
});

/*
 * The two edit-mode recurrence notes are COMPLEMENTS (#43).
 *
 * "It is unclear whether recurrence is intentionally creation-only" is the complaint the
 * issue actually raises, and silence is what caused it: before the series callout, an
 * edit form that simply omitted the series controls left the editor guessing whether the
 * setting was missing, hidden or broken. Answering only half of it — telling occurrences
 * of a series why they cannot change it, while a standalone event still says nothing —
 * would leave exactly that gap open for every event that is NOT part of a series.
 *
 * So edit mode owes an answer in BOTH shapes:
 *   `data-testid="series-locked-note"`             gated `$event && $event->recurrence_group !== null`
 *   `data-testid="recurrence-creation-only-note"`  gated `$event && $event->recurrence_group === null`
 *
 * One condition, negated. Exactly one of the two must therefore render for any stored
 * event — never both (contradictory advice), never neither (the silence that started
 * this). The counting assertion below is what makes "neither" fail; a pair of
 * assertDontSeeHtml() calls would pass happily on an edit form that says nothing at all.
 */

it('tells the editor that recurrence cannot be added afterwards when editing a standalone event', function () {
    $meetup = meetupWithActingUserAsLeader();

    $event = MeetupEvent::factory()->for($meetup)->create([
        'recurrence_type' => null,
        'recurrence_end_date' => null,
        'recurrence_group' => null,
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSeeHtml('data-testid="recurrence-creation-only-note"')
        ->assertDontSeeHtml('data-testid="series-locked-note"');
});

it('shows the series callout and not the creation-only note when editing a series occurrence', function () {
    $meetup = meetupWithActingUserAsLeader();

    $event = MeetupEvent::factory()->for($meetup)->create([
        'recurrence_type' => RecurrenceType::Weekly->value,
        'recurrence_end_date' => now()->addYear(),
        'recurrence_group' => (string) Str::uuid(),
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSeeHtml('data-testid="series-locked-note"')
        ->assertDontSeeHtml('data-testid="recurrence-creation-only-note"');
});

it('shows neither recurrence note when creating an event', function () {
    $meetup = meetupWithActingUserAsLeader();

    // Both notes speak about a setting that is already decided. While it is still open —
    // the switch and the series fields are right there — neither has anything to say.
    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertDontSeeHtml('data-testid="series-locked-note"')
        ->assertDontSeeHtml('data-testid="recurrence-creation-only-note"');
});

/**
 * The complement itself, over the four shapes an edit form can be opened on.
 *
 * The two shapes in the middle are the ones that separate `recurrence_group` from
 * `recurrence_type`, and they are why the count is asserted per shape rather than once:
 *
 *  - "pre-backfill series" carries a group and NO rule — the state
 *    2026_08_25_194948_group_existing_meetup_event_series left behind on every series
 *    that existed before P5. A gate keyed off `recurrence_type` would hand it the
 *    creation-only note and tell the editor of a real series that it is not one; the
 *    `$notes[$expected]` assertion falls with "Failed asserting that 0 is identical to 1".
 *  - "rule without a group" is the mirror image, writable through
 *    `POST/PATCH /api/meetup-events` since forever (see the third branch of
 *    Meetup::recalculateActivity()). It is a single event: a gate on `recurrence_type`
 *    would show it the series callout, and the same assertion falls on the other note.
 *
 * The `array_sum(...)` assertion is independent of both: it fails whenever an edit view
 * stops answering the recurrence question at all, whichever column someone gates on.
 */
it('renders exactly one of the two recurrence notes in edit mode', function (?RecurrenceType $rule, bool $belongsToSeries, string $expectedNote) {
    $meetup = meetupWithActingUserAsLeader();

    $event = MeetupEvent::factory()->for($meetup)->create([
        'recurrence_type' => $rule?->value,
        'recurrence_end_date' => $rule !== null ? now()->addYear() : null,
        'recurrence_group' => $belongsToSeries ? (string) Str::uuid() : null,
    ]);

    $html = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])->html();

    $notes = [
        'series-locked-note' => substr_count($html, 'data-testid="series-locked-note"'),
        'recurrence-creation-only-note' => substr_count($html, 'data-testid="recurrence-creation-only-note"'),
    ];

    // Exactly one note, and it is the right one — together these also rule out a form
    // that renders both, which would give contradictory advice about the same event.
    expect(array_sum($notes))->toBe(1)
        ->and($notes[$expectedNote])->toBe(1);
})->with([
    'standalone — neither rule nor group' => [null, false, 'recurrence-creation-only-note'],
    'rule without a group — single event written through the REST path' => [RecurrenceType::Weekly, false, 'recurrence-creation-only-note'],
    'series occurrence — rule and group' => [RecurrenceType::Weekly, true, 'series-locked-note'],
    'pre-backfill series — group, no rule' => [null, true, 'series-locked-note'],
]);

it('still offers the switch, the series fields and the series button when creating', function () {
    $meetup = meetupWithActingUserAsLeader();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertSee(__('Aktiviere diese Option, um mehrere Events mit regelmäßigen Abständen zu erstellen'))
        ->assertSee(__('Event erstellen'))
        ->assertDontSeeHtml('data-testid="series-locked-note"')
        ->set('seriesMode', true)
        ->assertSee(__('Datum des letzten Termins'))
        ->assertSee(__('Wiederholungstyp'))
        ->assertSee(__('Serientermine erstellen'))
        ->assertSee(__('Du bist dabei, mehrere Events zu erstellen.'));
});

it('renders the series switch inside the event fieldset, after the date and time grid', function () {
    $meetup = meetupWithActingUserAsLeader();

    $html = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])->html();

    $legend = mb_strpos($html, __('Event Details'));
    $timeGrid = mb_strpos($html, __('Um wie viel Uhr startet das Event?'));
    $toggle = mb_strpos($html, __('Aktiviere diese Option, um mehrere Events mit regelmäßigen Abständen zu erstellen'));
    $laterField = mb_strpos($html, __('Optional — ohne Titel erscheint der Name des Meetups.'));

    expect($legend)->not->toBeFalse()
        ->and($timeGrid)->not->toBeFalse()
        ->and($toggle)->not->toBeFalse()
        ->and($laterField)->not->toBeFalse()
        // Inside the fieldset, after the date/time grid, before the remaining fields.
        ->and($legend)->toBeLessThan($toggle)
        ->and($timeGrid)->toBeLessThan($toggle)
        ->and($toggle)->toBeLessThan($laterField);
});

it('still creates a whole series from the creation form', function () {
    $meetup = meetupWithActingUserAsLeader();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    // 2026-07-01, 07-08, 07-15, 07-22, 07-29 — inclusive to the end of the chosen day.
    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(5);
});

it('still requires an end date and a recurrence type when creating a series', function () {
    $meetup = meetupWithActingUserAsLeader();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '')
        ->set('recurrenceType', null)
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->call('save')
        ->assertHasErrors(['endDate' => 'required', 'recurrenceType' => 'required']);

    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(0);
});

it('lets the action bar wrap instead of forcing horizontal scrolling', function () {
    $meetup = meetupWithActingUserAsLeader();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertSeeHtml('class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-8 border-t border-gray-200 dark:border-gray-700"');
});
