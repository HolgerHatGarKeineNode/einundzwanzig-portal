<?php

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

it('rejects a guest creating a course with 401', function () {
    $lecturer = Lecturer::factory()->create();

    $this->postJson('/api/courses', [
        'name' => 'Specter Shield Lite Workshop',
        'lecturer_id' => $lecturer->id,
    ])->assertUnauthorized();
});

it('forbids a non-lecturer from creating a course', function () {
    Sanctum::actingAs(User::factory()->create(['is_lecturer' => false]));
    $lecturer = Lecturer::factory()->create();

    $this->postJson('/api/courses', [
        'name' => 'Specter Shield Lite Workshop',
        'lecturer_id' => $lecturer->id,
    ])->assertForbidden();
});

it('lets a lecturer create a course', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $lecturer = Lecturer::factory()->create();

    $this->postJson('/api/courses', [
        'name' => 'Specter Shield Lite Workshop',
        'lecturer_id' => $lecturer->id,
        'description' => 'Hardware-Wallet selbst bauen.',
    ])
        ->assertCreated()
        ->assertJsonPath('name', 'Specter Shield Lite Workshop');

    $this->assertDatabaseHas('courses', [
        'name' => 'Specter Shield Lite Workshop',
        'created_by' => $user->id,
    ]);
});

it('lets a lecturer create a course event', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $course = Course::factory()->create();
    $venue = Venue::factory()->create();

    $this->postJson('/api/course-events', [
        'course_id' => $course->id,
        'venue_id' => $venue->id,
        'from' => '2026-07-01 18:00:00',
        'to' => '2026-07-01 21:00:00',
        'link' => 'https://clavastack.com/produkt/specter-shield-lite-workshop',
    ])
        ->assertCreated()
        ->assertJsonPath('course_id', $course->id);

    $this->assertDatabaseHas('course_events', [
        'course_id' => $course->id,
        'venue_id' => $venue->id,
        'created_by' => $user->id,
    ]);
});

it('fails course event validation without required fields', function () {
    Sanctum::actingAs(User::factory()->lecturer()->create());

    $this->postJson('/api/course-events', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id', 'venue_id', 'from', 'to', 'link']);
});

it('returns only the authenticated user\'s own course events', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $other = User::factory()->lecturer()->create();

    CourseEvent::factory()->count(2)->create(['created_by' => $user->id]);
    CourseEvent::factory()->create(['created_by' => $other->id]);

    $response = $this->getJson('/api/course-events');

    $response->assertSuccessful();
    expect($response->json())->toHaveCount(2);
    collect($response->json())->each(
        fn ($event) => expect($event['created_by'])->toBe($user->id)
    );
});

it('filters own course events by course_id', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());

    $event = CourseEvent::factory()->create(['created_by' => $user->id]);
    CourseEvent::factory()->create(['created_by' => $user->id]);

    $response = $this->getJson('/api/course-events?course_id='.$event->course_id);

    $response->assertSuccessful();
    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.id'))->toBe($event->id);
});

it('lets the owner update their course event', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $event = CourseEvent::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/course-events/'.$event->id, [
        'link' => 'https://einundzwanzig.space/courses/updated',
    ])
        ->assertSuccessful()
        ->assertJsonPath('link', 'https://einundzwanzig.space/courses/updated');
});

it('forbids updating a course event owned by someone else', function () {
    $owner = User::factory()->lecturer()->create();
    $event = CourseEvent::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->lecturer()->create());

    $this->patchJson('/api/course-events/'.$event->id, [
        'link' => 'https://einundzwanzig.space/courses/hijacked',
    ])->assertForbidden();
});
