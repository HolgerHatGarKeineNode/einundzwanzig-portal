<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Course\CreateCourseTool;
use App\Mcp\Tools\Course\UpdateCourseTool;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

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
 * MCP und REST beantworten dieselbe Frage — jetzt auch gleich.
 *
 * Hier stand die Umkehrung: „verbietet einem Nicht-Referenten, einen Kurs anzulegen".
 * Sie beschrieb eine Inline-Pruefung im Werkzeug, die P1 aus `CoursePolicy::create()`
 * bereits entfernt hatte. Damit sagten die beiden Tueren zum selben Vorgang
 * Verschiedenes: REST liess den Nutzer durch, MCP wies ihn ab.
 *
 * Der Test prueft deshalb beide Wege in einem Lauf. Ein Test, der nur die eine Tuer
 * misst, kann dieses Auseinanderlaufen nicht sehen — es ist ja genau der Unterschied
 * ZWISCHEN ihnen.
 */
it('lets a non-lecturer create a course through MCP, exactly as REST does', function () {
    $user = User::factory()->notLecturer()->create();
    $lecturer = Lecturer::factory()->create();

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateCourseTool::class, [
            'name' => 'Kurs ueber MCP',
            'lecturer_id' => $lecturer->id,
        ])
        ->assertOk();

    Sanctum::actingAs($user);
    $this->postJson('/api/courses', [
        'name' => 'Kurs ueber REST',
        'lecturer_id' => $lecturer->id,
    ])->assertCreated();

    expect(Course::query()->where('created_by', $user->id)->pluck('name')->sort()->values()->all())
        ->toBe(['Kurs ueber MCP', 'Kurs ueber REST']);
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
