<?php

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Livewire\Livewire;

function meetupForLocationValidation(): Meetup
{
    return Meetup::factory()->create(['created_by' => actingAsUser()->id]);
}

/**
 * @return array<string, mixed>
 */
function validOsmPlace(): array
{
    return [
        'osm_type' => 'node',
        'osm_id' => 240109189,
        'osm_name' => 'VHS Lippstadt',
        'osm_address' => 'VHS Lippstadt, Barthstraße 2, 59555 Lippstadt, Deutschland',
        'osm_lat' => 51.6739,
        'osm_lon' => 8.3444,
    ];
}

it('clears the required location once a structured OSM place replaces it', function () {
    $meetup = meetupForLocationValidation();
    $event = MeetupEvent::factory()->for($meetup)->create([
        'location' => 'Café Test',
        'recurrence_type' => null,
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSet('location', 'Café Test')
        ->set('osmPlace', validOsmPlace())
        ->set('location', '')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->location)->toBeNull()
        ->and($event->osm_id)->toBe(240109189)
        ->and($event->osm_name)->toBe('VHS Lippstadt');
});

it('still rejects an event with neither a text location nor an OSM place', function () {
    $meetup = meetupForLocationValidation();
    $event = MeetupEvent::factory()->for($meetup)->create([
        'location' => 'Café Test',
        'recurrence_type' => null,
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->set('location', '')
        ->call('save')
        ->assertHasErrors(['location' => 'required_without']);

    expect($event->refresh()->location)->toBe('Café Test');
});

it('still accepts a plain text location without any OSM place', function () {
    $meetup = meetupForLocationValidation();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Café Test')
        ->set('description', 'Ein Test-Event')
        ->set('link', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(MeetupEvent::query()->latest('id')->first()->location)->toBe('Café Test');
});
