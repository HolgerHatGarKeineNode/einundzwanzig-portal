<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Course\CreateCourseTool;
use App\Mcp\Tools\Course\UpdateCourseTool;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\User;

it('lets a lecturer create a course and stamps created_by', function () {
    $user = User::factory()->lecturer()->create();
    $lecturer = Lecturer::factory()->create();

    $response = EinundzwanzigServer::actingAs($user)->tool(CreateCourseTool::class, [
        'name' => 'Bitcoin Grundlagen',
        'lecturer_id' => $lecturer->id,
    ]);

    $response->assertOk()->assertSee('Bitcoin Grundlagen');

    $this->assertDatabaseHas('courses', [
        'name' => 'Bitcoin Grundlagen',
        'created_by' => $user->id,
    ]);
});

/**
 * Das MCP-Werkzeug prueft `is_lecturer` selbst (CreateCourseTool), nicht ueber die
 * Policy. Der Test behaelt damit seinen Gegenstand, obwohl `CoursePolicy::create()` das
 * Flag nicht mehr kennt — die REST-API laesst denselben Nutzer inzwischen durch.
 */
it('forbids a non-lecturer from creating a course', function () {
    $user = User::factory()->notLecturer()->create();
    $lecturer = Lecturer::factory()->create();

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateCourseTool::class, [
            'name' => 'Verbotener Kurs',
            'lecturer_id' => $lecturer->id,
        ])
        ->assertHasErrors();
});

it('fails validation for missing fields', function () {
    EinundzwanzigServer::actingAs(User::factory()->lecturer()->create())
        ->tool(CreateCourseTool::class, [])
        ->assertHasErrors();
});

it('lets the owner update a course', function () {
    $user = User::factory()->lecturer()->create();
    $course = Course::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateCourseTool::class, ['id' => $course->id, 'name' => 'Aktualisierter Kurs'])
        ->assertOk()
        ->assertSee('Aktualisierter Kurs');
});

it('forbids updating someone elses course', function () {
    $owner = User::factory()->lecturer()->create();
    $course = Course::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->lecturer()->create())
        ->tool(UpdateCourseTool::class, ['id' => $course->id, 'name' => 'Hijack'])
        ->assertHasErrors();
});
