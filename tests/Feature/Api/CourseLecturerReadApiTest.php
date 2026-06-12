<?php

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;

it('limits GET /api/courses to 10 entries without withDetails', function () {
    Course::factory()->count(11)->create();

    $this->getJson('/api/courses')
        ->assertSuccessful()
        ->assertJsonCount(10);
});

it('returns all courses with details on GET /api/courses?withDetails', function () {
    $lecturer = Lecturer::factory()->create();
    $courses = Course::factory()->count(11)->create(['lecturer_id' => $lecturer->id]);
    $event = CourseEvent::factory()->create([
        'course_id' => $courses->first()->id,
        'from' => now()->addDays(3),
        'to' => now()->addDays(3)->addHours(2),
    ]);
    // Vergangene Events dürfen next_event nicht beeinflussen.
    CourseEvent::factory()->create([
        'course_id' => $courses->first()->id,
        'from' => now()->subDays(3),
        'to' => now()->subDays(3)->addHours(2),
    ]);

    $response = $this->getJson('/api/courses?withDetails')
        ->assertSuccessful()
        ->assertJsonCount(11);

    $first = collect($response->json())->firstWhere('id', $courses->first()->id);

    expect($first)
        ->toHaveKeys(['id', 'name', 'image', 'description', 'next_event', 'lecturer'])
        ->and($first['lecturer']['id'])->toBe($lecturer->id)
        ->and($first['next_event'])->toStartWith($event->from->format('Y-m-d'));
});

it('shows a course with upcoming events, venue and city on GET /api/courses/{course}', function () {
    $course = Course::factory()->create();

    $future = CourseEvent::factory()->create([
        'course_id' => $course->id,
        'from' => now()->addWeek(),
        'to' => now()->addWeek()->addHours(2),
    ]);
    CourseEvent::factory()->create([
        'course_id' => $course->id,
        'from' => now()->subWeek(),
        'to' => now()->subWeek()->addHours(2),
    ]);

    $this->getJson('/api/courses/'.$course->id)
        ->assertSuccessful()
        ->assertJsonPath('id', $course->id)
        ->assertJsonPath('name', $course->name)
        ->assertJsonPath('lecturer.id', $course->lecturer_id)
        ->assertJsonCount(1, 'events')
        ->assertJsonPath('events.0.id', $future->id)
        ->assertJsonPath('events.0.venue.id', $future->venue_id)
        ->assertJsonPath('events.0.venue.city.name', $future->venue->city->name)
        ->assertJsonPath('events.0.venue.city.country.code', $future->venue->city->country->code);
});

it('returns 404 for an unknown course on GET /api/courses/{course}', function () {
    $this->getJson('/api/courses/999999')->assertNotFound();
});

it('limits GET /api/lecturers to 10 entries without withDetails', function () {
    Lecturer::factory()->count(11)->create();

    $this->getJson('/api/lecturers')
        ->assertSuccessful()
        ->assertJsonCount(10);
});

it('returns all lecturers with details on GET /api/lecturers?withDetails', function () {
    $lecturers = Lecturer::factory()->count(11)->create();
    $course = Course::factory()->create(['lecturer_id' => $lecturers->first()->id]);
    CourseEvent::factory()->count(2)->create([
        'course_id' => $course->id,
        'from' => now()->addDays(5),
        'to' => now()->addDays(5)->addHours(2),
    ]);

    $response = $this->getJson('/api/lecturers?withDetails')
        ->assertSuccessful()
        ->assertJsonCount(11);

    $first = collect($response->json())->firstWhere('id', $lecturers->first()->id);

    expect($first)
        ->toHaveKeys(['id', 'name', 'subtitle', 'image', 'future_events_count'])
        ->and($first['future_events_count'])->toBe(2);
});

it('shows a lecturer profile with courses on GET /api/lecturers/{lecturer}', function () {
    $lecturer = Lecturer::factory()->create([
        'nostr' => 'npub1examplelecturer',
        'website' => 'https://einundzwanzig.space',
    ]);
    $course = Course::factory()->create(['lecturer_id' => $lecturer->id]);
    $event = CourseEvent::factory()->create([
        'course_id' => $course->id,
        'from' => now()->addDays(10),
        'to' => now()->addDays(10)->addHours(2),
    ]);

    $response = $this->getJson('/api/lecturers/'.$lecturer->id)
        ->assertSuccessful()
        ->assertJsonPath('id', $lecturer->id)
        ->assertJsonPath('nostr', 'npub1examplelecturer')
        ->assertJsonPath('website', 'https://einundzwanzig.space')
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.id', $course->id);

    expect($response->json('courses.0.next_event'))->toStartWith($event->from->format('Y-m-d'));
});

it('returns 404 for an unknown lecturer on GET /api/lecturers/{lecturer}', function () {
    $this->getJson('/api/lecturers/999999')->assertNotFound();
});
