<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Meetup\CreateMeetupTool;
use App\Mcp\Tools\Meetup\ListMyMeetupsTool;
use App\Mcp\Tools\Meetup\ShowMyMeetupTool;
use App\Mcp\Tools\Meetup\UpdateMeetupTool;
use App\Models\City;
use App\Models\Meetup;
use App\Models\User;

it('lets an authenticated user create a meetup and stamps created_by', function () {
    $user = User::factory()->create();
    $city = City::factory()->create();

    $response = EinundzwanzigServer::actingAs($user)->tool(CreateMeetupTool::class, [
        'name' => 'Einundzwanzig Ansbach',
        'city_id' => $city->id,
    ]);

    $response->assertOk()->assertSee('Einundzwanzig Ansbach');

    $this->assertDatabaseHas('meetups', [
        'name' => 'Einundzwanzig Ansbach',
        'created_by' => $user->id,
    ]);
});

it('fails validation for missing fields', function () {
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(CreateMeetupTool::class, [])
        ->assertHasErrors();
});

it('lets the owner update a meetup', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupTool::class, ['id' => $meetup->id, 'name' => 'Plan B Lugano'])
        ->assertOk()
        ->assertSee('Plan B Lugano');
});

it('forbids updating someone elses meetup', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateMeetupTool::class, ['id' => $meetup->id, 'name' => 'Hijack'])
        ->assertHasErrors();
});

it('returns only own meetups in the mine list', function () {
    $user = User::factory()->create();
    Meetup::factory()->count(2)->create(['created_by' => $user->id]);
    Meetup::factory()->create(['created_by' => User::factory()->create()->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyMeetupsTool::class)
        ->assertOk();
});

it('forbids viewing someone elses meetup in mine show', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(ShowMyMeetupTool::class, ['id' => $meetup->id])
        ->assertHasErrors();
});
