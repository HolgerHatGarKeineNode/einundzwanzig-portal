<?php

use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a guest creating a course with 401', function () {
    $lecturer = Lecturer::factory()->create();

    $this->postJson('/api/courses', [
        'name' => 'Specter Shield Lite Workshop',
        'lecturer_id' => $lecturer->id,
    ])->assertUnauthorized();
});

/**
 * Frueher stand hier das Gegenteil: ohne `is_lecturer` ein 403.
 *
 * Der Gegenstand ist mit der Pruefung weggefallen — das Flag wird bei jeder
 * Registrierung gesetzt, hat also nie jemanden ausgesperrt. Der Test bleibt als
 * Nachweis der jetzt geltenden Regel stehen, statt ersatzlos zu verschwinden: wer
 * angemeldet ist, darf anlegen; wem etwas gehoert, entscheidet erst `update`.
 */
it('lets a user without the lecturer badge create a course', function () {
    Sanctum::actingAs($user = User::factory()->notLecturer()->create());
    $lecturer = Lecturer::factory()->create();

    $this->postJson('/api/courses', [
        'name' => 'Specter Shield Lite Workshop',
        'lecturer_id' => $lecturer->id,
    ])->assertCreated();

    $this->assertDatabaseHas('courses', [
        'name' => 'Specter Shield Lite Workshop',
        'created_by' => $user->id,
    ]);
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
        ->assertJsonPath('data.name', 'Specter Shield Lite Workshop')
        ->assertJsonPath('data.created_by', $user->id);

    $this->assertDatabaseHas('courses', [
        'name' => 'Specter Shield Lite Workshop',
        'created_by' => $user->id,
    ]);
});

it('fails course validation without required fields', function () {
    Sanctum::actingAs(User::factory()->lecturer()->create());

    $this->postJson('/api/courses', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'lecturer_id']);
});

it('lets the owner update their course', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $course = Course::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/courses/'.$course->id, [
        'name' => 'Aktualisierter Kurs',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Aktualisierter Kurs');
});

it('forbids updating a course owned by someone else', function () {
    $owner = User::factory()->lecturer()->create();
    $course = Course::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->lecturer()->create());

    $this->patchJson('/api/courses/'.$course->id, [
        'name' => 'Übernommen',
    ])->assertForbidden();
});

it('lets a lecturer create a course event', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $course = Course::factory()->create();
    $city = City::factory()->create();

    $this->postJson('/api/course-events', [
        'course_id' => $course->id,
        'city_id' => $city->id,
        'from' => '2026-07-01 18:00:00',
        'to' => '2026-07-01 21:00:00',
        'link' => 'https://clavastack.com/produkt/specter-shield-lite-workshop',
    ])
        ->assertCreated()
        ->assertJsonPath('data.course_id', $course->id);

    $this->assertDatabaseHas('course_events', [
        'course_id' => $course->id,
        'city_id' => $city->id,
        'created_by' => $user->id,
    ]);
});

/**
 * Auch hier stand frueher das Gegenteil (403 ohne `is_lecturer`). Siehe oben: die
 * Bedingung ist gefallen, weil sie nie eine war. Der Test haelt die neue Regel fest.
 */
it('lets a user without the lecturer badge create a course event', function () {
    Sanctum::actingAs($user = User::factory()->notLecturer()->create());
    $course = Course::factory()->create();
    $city = City::factory()->create();

    $this->postJson('/api/course-events', [
        'course_id' => $course->id,
        'city_id' => $city->id,
        'from' => '2026-07-01 18:00:00',
        'to' => '2026-07-01 21:00:00',
        'link' => 'https://example.com/event',
    ])->assertCreated();

    $this->assertDatabaseHas('course_events', [
        'course_id' => $course->id,
        'created_by' => $user->id,
    ]);
});

it('fails course event validation without required fields', function () {
    Sanctum::actingAs(User::factory()->lecturer()->create());

    $this->postJson('/api/course-events', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['course_id', 'city_id', 'from', 'to', 'link']);
});

it('returns only the authenticated user\'s own course events', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $other = User::factory()->lecturer()->create();

    CourseEvent::factory()->count(2)->create(['created_by' => $user->id]);
    CourseEvent::factory()->create(['created_by' => $other->id]);

    $response = $this->getJson('/api/course-events');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
    collect($response->json('data'))->each(
        fn ($event) => expect($event['created_by'])->toBe($user->id)
    );
});

it('filters own course events by course_id', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());

    $event = CourseEvent::factory()->create(['created_by' => $user->id]);
    CourseEvent::factory()->create(['created_by' => $user->id]);

    $response = $this->getJson('/api/course-events?course_id='.$event->course_id);

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($event->id);
});

it('lets the owner update their course event', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $event = CourseEvent::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/course-events/'.$event->id, [
        'link' => 'https://einundzwanzig.space/courses/updated',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.link', 'https://einundzwanzig.space/courses/updated');
});

it('forbids updating a course event owned by someone else', function () {
    $owner = User::factory()->lecturer()->create();
    $event = CourseEvent::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->lecturer()->create());

    $this->patchJson('/api/course-events/'.$event->id, [
        'link' => 'https://einundzwanzig.space/courses/hijacked',
    ])->assertForbidden();
});

it('accepts a patch that moves only the end time', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $event = CourseEvent::factory()->create([
        'created_by' => $user->id,
        'from' => now()->addWeek()->setTime(9, 0),
        'to' => now()->addWeek()->setTime(12, 0),
    ]);

    /*
     * Without `from` in the payload, an unconditional `after_or_equal:from` makes Laravel
     * read "from" as a date literal rather than a field reference — the request then fails
     * or compares against nonsense. It has to stay conditional.
     */
    $this->patchJson('/api/course-events/'.$event->id, [
        'to' => now()->addWeek()->setTime(14, 0)->toIso8601String(),
    ])->assertSuccessful();
});

it('still rejects an end that precedes the start when both are sent', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $event = CourseEvent::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/course-events/'.$event->id, [
        'from' => now()->addWeek()->setTime(12, 0)->toIso8601String(),
        'to' => now()->addWeek()->setTime(9, 0)->toIso8601String(),
    ])->assertJsonValidationErrors(['to']);
});

it('refuses half an osm pair', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $event = CourseEvent::factory()->create(['created_by' => $user->id]);

    // An id without a type does not identify anything — ids are only unique per type.
    $this->patchJson('/api/course-events/'.$event->id, [
        'osm_id' => 240109189,
    ])->assertJsonValidationErrors(['osm_type']);

    $this->patchJson('/api/course-events/'.$event->id, [
        'osm_type' => 'node',
    ])->assertJsonValidationErrors(['osm_id']);
});

it('stores a complete osm place', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $event = CourseEvent::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/course-events/'.$event->id, [
        'osm_type' => 'way',
        'osm_id' => 123456,
        'osm_name' => 'Bürgerhaus',
        'osm_lat' => 49.2799,
        'osm_lon' => 11.4622,
    ])->assertSuccessful()
        ->assertJsonPath('data.osm_type', 'way')
        ->assertJsonPath('data.osm_id', 123456);
});

it('rejects an out-of-range coordinate', function () {
    Sanctum::actingAs($user = User::factory()->lecturer()->create());
    $event = CourseEvent::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/course-events/'.$event->id, [
        'osm_type' => 'node',
        'osm_id' => 1,
        'osm_lat' => 95.0,
    ])->assertJsonValidationErrors(['osm_lat']);
});
