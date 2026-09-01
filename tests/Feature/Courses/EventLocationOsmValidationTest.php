<?php

use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use Livewire\Livewire;

function courseForLocationValidation(): Course
{
    return Course::factory()->create(['created_by' => actingAsUser()->id]);
}

/**
 * @return array<string, mixed>
 */
function validCourseOsmPlace(): array
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
    $course = courseForLocationValidation();
    $event = CourseEvent::factory()->for($course)->create([
        'location' => 'Café Test',
        'created_by' => auth()->id(),
    ]);

    Livewire::test('courses.create-edit-events', ['course' => $course, 'event' => $event])
        ->assertSet('location', 'Café Test')
        ->set('osmPlace', validCourseOsmPlace())
        ->set('location', '')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->location)->toBeNull()
        ->and($event->osm_id)->toBe(240109189)
        ->and($event->osm_name)->toBe('VHS Lippstadt');
});

it('still rejects an event with neither a text location nor an OSM place', function () {
    $course = courseForLocationValidation();
    $event = CourseEvent::factory()->for($course)->create([
        'location' => 'Café Test',
        'created_by' => auth()->id(),
    ]);

    Livewire::test('courses.create-edit-events', ['course' => $course, 'event' => $event])
        ->set('location', '')
        ->call('save')
        ->assertHasErrors(['location' => 'required_without']);

    expect($event->refresh()->location)->toBe('Café Test');
});

it('still accepts a plain text location without any OSM place', function () {
    $course = courseForLocationValidation();
    $city = City::factory()->create();

    Livewire::test('courses.create-edit-events', ['course' => $course])
        ->set('fromDate', now()->addWeek()->format('Y-m-d'))
        ->set('fromTime', '19:00')
        ->set('toDate', now()->addWeek()->format('Y-m-d'))
        ->set('toTime', '21:00')
        ->set('city_id', $city->id)
        ->set('location', 'Café Test')
        ->set('link', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(CourseEvent::query()->latest('id')->first()->location)->toBe('Café Test');
});
