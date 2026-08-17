<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->course = Course::factory()->create();
    $this->lecturer = Lecturer::factory()->create();
    $this->event = CourseEvent::factory()->create([
        'course_id' => $this->course->id,
        'city_id' => $city->id,
    ]);
});

/**
 * The course-event form is only for whoever may edit the course itself.
 *
 * forceFill, because `created_by` is not in Course::$fillable — a plain update() would be
 * dropped by mass-assignment protection without saying so, and every test here would then
 * pass for the wrong reason.
 */
function ownsTheCourse(): void
{
    $user = actingAsUser();
    test()->course->forceFill(['created_by' => $user->id])->save();
}

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
    ownsTheCourse();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])->assertStatus(200);
});

it('refuses to manage events of a course somebody else owns', function () {
    actingAsUser();

    // The route only sits behind `auth`, so without an explicit check any logged-in user
    // could create, edit and delete dates on any course in the system.
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->assertStatus(403);
});

it('mounts courses.create-edit-events for existing event', function () {
    ownsTheCourse();
    Livewire::test('courses.create-edit-events', [
        'course' => $this->course,
        'event' => $this->event,
    ])->assertStatus(200);
});

it('does not crash with PropertyNotFoundException when fromDate is set to null', function () {
    ownsTheCourse();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->set('fromDate', null)
        ->assertStatus(200)
        ->assertSet('fromDate', null);
});

it('does not crash when toDate is set to null', function () {
    ownsTheCourse();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->set('toDate', null)
        ->assertStatus(200)
        ->assertSet('toDate', null);
});

it('does not crash when fromTime is set to null', function () {
    ownsTheCourse();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->set('fromTime', null)
        ->assertStatus(200);
});

it('does not crash when toTime is set to null', function () {
    ownsTheCourse();
    Livewire::test('courses.create-edit-events', ['course' => $this->course])
        ->set('toTime', null)
        ->assertStatus(200);
});
