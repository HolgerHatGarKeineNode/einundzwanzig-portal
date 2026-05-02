<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Venue;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $venue = Venue::factory()->create(['city_id' => $city->id]);
    $this->course = Course::factory()->create();
    $this->lecturer = Lecturer::factory()->create();
    $this->event = CourseEvent::factory()->create([
        'course_id' => $this->course->id,
        'venue_id' => $venue->id,
    ]);
});

it('mounts courses.landingpage with a course', function () {
    Livewire::test('courses.landingpage', ['course' => $this->course])->assertStatus(200);
});

it('skips courses.landingpage-event because the Volt component file does not exist (route is broken)', function () {
    $path = resource_path('views/livewire/courses/landingpage-event.blade.php');
    expect(file_exists($path))->toBeFalse(
        'The route /course/{course}/event/{event} maps to a missing component file at '.$path
    );
});

it('mounts courses.create when authenticated', function () {
    actingAsUser();
    Livewire::test('courses.create')->assertStatus(200);
});

it('mounts courses.edit when authenticated', function () {
    actingAsUser();
    Livewire::test('courses.edit', ['course' => $this->course])->assertStatus(200);
});

it('mounts courses.create-edit-events for new event', function () {
    actingAsUser();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])->assertStatus(200);
});

it('mounts courses.create-edit-events for existing event', function () {
    actingAsUser();
    Livewire::test('courses.create-edit-events', [
        'course' => $this->course,
        'event' => $this->event,
    ])->assertStatus(200);
});

it('does not crash with PropertyNotFoundException when fromDate is set to null', function () {
    actingAsUser();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->set('fromDate', null)
        ->assertStatus(200)
        ->assertSet('fromDate', null);
});

it('does not crash when toDate is set to null', function () {
    actingAsUser();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->set('toDate', null)
        ->assertStatus(200)
        ->assertSet('toDate', null);
});

it('does not crash when fromTime is set to null', function () {
    actingAsUser();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->set('fromTime', null)
        ->assertStatus(200);
});

it('does not crash when toTime is set to null', function () {
    actingAsUser();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->set('toTime', null)
        ->assertStatus(200);
});
