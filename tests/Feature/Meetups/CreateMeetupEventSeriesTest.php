<?php

use App\Enums\RecurrenceType;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Livewire\Livewire;

it('creates a weekly series via the web editor using the shared action', function () {
    actingAsUser();
    $meetup = Meetup::factory()->create();

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

    // The web editor parses the end date at midnight, so the occurrence on the end
    // date's evening falls outside the range: 2026-07-01, 07-08, 07-15, 07-22 = 4.
    // The shared action yields the identical result for the same inputs.
    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(4);
});

it('previews the same dates it will create', function () {
    actingAsUser();
    $meetup = Meetup::factory()->create();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->assertSet('previewDates', fn ($dates) => count($dates) === 4);
});
