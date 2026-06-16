<?php

use App\Enums\RecurrenceType;
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
