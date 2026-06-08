<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Lecturer\CreateLecturerTool;
use App\Mcp\Tools\Lecturer\ListMyLecturersTool;
use App\Mcp\Tools\Lecturer\ShowMyLecturerTool;
use App\Mcp\Tools\Lecturer\UpdateLecturerTool;
use App\Models\Lecturer;
use App\Models\User;

it('lets an authenticated user create a lecturer and stamps created_by', function () {
    $user = User::factory()->create();

    $response = EinundzwanzigServer::actingAs($user)->tool(CreateLecturerTool::class, [
        'name' => 'Saifedean Ammous',
    ]);

    $response->assertOk()->assertSee('Saifedean Ammous');

    $this->assertDatabaseHas('lecturers', [
        'name' => 'Saifedean Ammous',
        'created_by' => $user->id,
    ]);
});

it('fails validation for missing fields', function () {
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(CreateLecturerTool::class, [])
        ->assertHasErrors();
});

it('lets the owner update a lecturer', function () {
    $user = User::factory()->create();
    $lecturer = Lecturer::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateLecturerTool::class, ['id' => $lecturer->id, 'name' => 'Knut Svanholm'])
        ->assertOk()
        ->assertSee('Knut Svanholm');
});

it('forbids updating someone elses lecturer', function () {
    $owner = User::factory()->create();
    $lecturer = Lecturer::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateLecturerTool::class, ['id' => $lecturer->id, 'name' => 'Hijack'])
        ->assertHasErrors();
});

it('returns only own lecturers in the mine list', function () {
    $user = User::factory()->create();
    Lecturer::factory()->count(2)->create(['created_by' => $user->id]);
    Lecturer::factory()->create(['created_by' => User::factory()->create()->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyLecturersTool::class)
        ->assertOk();
});

it('forbids viewing someone elses lecturer in mine show', function () {
    $owner = User::factory()->create();
    $lecturer = Lecturer::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(ShowMyLecturerTool::class, ['id' => $lecturer->id])
        ->assertHasErrors();
});
