<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\MeetupEvent\CreateMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\ListMyMeetupEventsTool;
use App\Mcp\Tools\MeetupEvent\ShowMyMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\UpdateMeetupEventTool;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;

it('lets an authenticated user create a meetup event and stamps created_by', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create();

    $response = EinundzwanzigServer::actingAs($user)->tool(CreateMeetupEventTool::class, [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
    ]);

    $response->assertOk()->assertSee('Marktplatz');

    $this->assertDatabaseHas('meetup_events', [
        'location' => 'Marktplatz',
        'created_by' => $user->id,
    ]);
});

it('fails validation for missing fields', function () {
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(CreateMeetupEventTool::class, [])
        ->assertHasErrors();
});

it('lets the owner update a meetup event', function () {
    $user = User::factory()->create();
    $meetupEvent = MeetupEvent::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $meetupEvent->id, 'location' => 'Rathaus'])
        ->assertOk()
        ->assertSee('Rathaus');
});

it('forbids updating someone elses meetup event', function () {
    $owner = User::factory()->create();
    $meetupEvent = MeetupEvent::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateMeetupEventTool::class, ['id' => $meetupEvent->id, 'location' => 'Hijack'])
        ->assertHasErrors();
});

it('returns only own meetup events in the mine list', function () {
    $user = User::factory()->create();
    MeetupEvent::factory()->count(2)->create(['created_by' => $user->id]);
    MeetupEvent::factory()->create(['created_by' => User::factory()->create()->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyMeetupEventsTool::class)
        ->assertOk();
});

it('forbids viewing someone elses meetup event in mine show', function () {
    $owner = User::factory()->create();
    $meetupEvent = MeetupEvent::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(ShowMyMeetupEventTool::class, ['id' => $meetupEvent->id])
        ->assertHasErrors();
});
