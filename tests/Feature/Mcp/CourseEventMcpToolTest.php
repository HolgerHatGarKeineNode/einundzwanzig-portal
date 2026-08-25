<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\CourseEvent\CreateCourseEventTool;
use App\Mcp\Tools\CourseEvent\ListMyCourseEventsTool;
use App\Mcp\Tools\CourseEvent\UpdateCourseEventTool;
use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Wie beim Kurs-Zwilling: MCP und REST beantworten dieselbe Frage und mussten dasselbe
 * sagen. Die fruehere Umkehrung dieses Tests beschrieb eine Inline-Pruefung, die P1 aus
 * `CourseEventPolicy::create()` entfernt hatte — MCP wies ab, wo REST durchliess.
 */
it('lets a non-lecturer create a course event through MCP, exactly as REST does', function () {
    $user = User::factory()->notLecturer()->create();
    $course = Course::factory()->create();
    $city = City::factory()->create();

    $payload = [
        'course_id' => $course->id,
        'city_id' => $city->id,
        'from' => '2026-07-01 18:00:00',
        'to' => '2026-07-01 21:00:00',
        'link' => 'https://clavastack.com/produkt/specter-shield-lite-workshop',
    ];

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateCourseEventTool::class, $payload)
        ->assertOk();

    Sanctum::actingAs($user);
    $this->postJson('/api/course-events', $payload)->assertCreated();

    expect(CourseEvent::query()->where('created_by', $user->id)->count())->toBe(2);
});

it('lets a lecturer create a course event and stamps created_by', function () {
    $user = User::factory()->lecturer()->create();
    $course = Course::factory()->create();
    $city = City::factory()->create();

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateCourseEventTool::class, [
            'course_id' => $course->id,
            'city_id' => $city->id,
            'from' => '2026-07-01 18:00:00',
            'to' => '2026-07-01 21:00:00',
            'link' => 'https://clavastack.com/produkt/specter-shield-lite-workshop',
        ])
        ->assertOk();

    $this->assertDatabaseHas('course_events', [
        'course_id' => $course->id,
        'city_id' => $city->id,
        'created_by' => $user->id,
    ]);
});

it('fails validation for missing fields', function () {
    EinundzwanzigServer::actingAs(User::factory()->lecturer()->create())
        ->tool(CreateCourseEventTool::class, [])
        ->assertHasErrors();
});

it('lets the owner update their course event', function () {
    $user = User::factory()->lecturer()->create();
    $event = CourseEvent::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateCourseEventTool::class, [
            'id' => $event->id,
            'link' => 'https://einundzwanzig.space/courses/updated',
        ])
        ->assertOk()
        ->assertSee('https://einundzwanzig.space/courses/updated');
});

it('forbids updating a course event owned by someone else', function () {
    $owner = User::factory()->lecturer()->create();
    $event = CourseEvent::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->lecturer()->create())
        ->tool(UpdateCourseEventTool::class, [
            'id' => $event->id,
            'link' => 'https://einundzwanzig.space/courses/hijacked',
        ])
        ->assertHasErrors();
});

it('returns only own course events in the mine list', function () {
    $user = User::factory()->lecturer()->create();
    $other = User::factory()->lecturer()->create();

    CourseEvent::factory()->count(2)->create(['created_by' => $user->id]);
    CourseEvent::factory()->create(['created_by' => $other->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyCourseEventsTool::class)
        ->assertOk();
});
