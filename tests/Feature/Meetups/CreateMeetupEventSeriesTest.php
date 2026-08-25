<?php

use App\Enums\RecurrenceType;
use App\Models\ApiChange;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Livewire\Livewire;

it('creates a weekly series via the web editor using the shared action', function () {
    // Termin-Verwaltung erfordert Leaderschaft; Ersteller ist per Hook Leader.
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('link', 'https://einundzwanzig.space')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    // Das Enddatum aus dem Datums-Picker gilt inklusiv bis zum Tagesende
    // (endOfDay), daher ist das Vorkommen am Enddatum-Abend dabei:
    // 2026-07-01, 07-08, 07-15, 07-22, 07-29 = 5. Deterministisch, unabhängig
    // von der Laufzeit-Uhrzeit.
    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(5);
});

it('previews the same dates it will create', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->assertSet('previewDates', fn ($dates) => count($dates) === 5);
});

it('gives every occurrence created in the editor the same series identity', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('recurrenceDayOfWeek', 'wednesday')
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('link', 'https://einundzwanzig.space')
        ->call('save')
        ->assertHasNoErrors();

    $events = MeetupEvent::where('meetup_id', $meetup->id)->orderBy('start')->get();

    expect($events)->toHaveCount(5)
        ->and($events->pluck('recurrence_group')->unique())->toHaveCount(1)
        ->and($events->first()->recurrence_group)->not->toBeNull();

    foreach ($events as $event) {
        // Seit P6 traegt `recurrence_type` einen echten Enum-Cast (die alte Eigenschaft
        // `$enumCasts` gab es in Eloquent nicht und sie lief ins Leere).
        expect($event->recurrence_type)->toBe(RecurrenceType::Weekly)
            ->and($event->recurrence_day_of_week)->toBe('wednesday')
            ->and($event->recurrence_interval)->toBe(1)
            ->and($event->recurrence_end_date)->not->toBeNull();
    }
});

it('records one meetup change for an editor series, not one per occurrence', function () {
    config()->set('einundzwanzig.change_log.enabled', true);

    $meetup = Meetup::factory()->create([
        'created_by' => actingAsUser()->id,
        'is_active' => false,
        'last_event_at' => null,
    ]);

    $before = ApiChange::where('resource', 'meetup')->where('resource_id', $meetup->id)->count();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', now()->subMonths(4)->format('Y-m-d'))
        ->set('startTime', '18:00')
        ->set('endDate', now()->subMonths(3)->format('Y-m-d'))
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('link', 'https://einundzwanzig.space')
        ->call('save')
        ->assertHasNoErrors();

    $occurrences = MeetupEvent::where('meetup_id', $meetup->id)->count();
    $after = ApiChange::where('resource', 'meetup')->where('resource_id', $meetup->id)->count();

    expect($occurrences)->toBeGreaterThan(1)
        ->and($after - $before)->toBe(1);
});
