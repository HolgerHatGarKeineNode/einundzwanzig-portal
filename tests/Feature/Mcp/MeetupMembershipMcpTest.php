<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Meetup\AddMeetupToMineTool;
use App\Mcp\Tools\Meetup\CreateMeetupTool;
use App\Mcp\Tools\Meetup\ListMyMeetupsTool;
use App\Mcp\Tools\MeetupEvent\CreateMeetupEventTool;
use App\Models\City;
use App\Models\Meetup;
use App\Models\User;

it('adds an existing foreign meetup to my meetups as a member', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['name' => 'Einundzwanzig Dortmund', 'created_by' => $owner->id]);
    $user = User::factory()->create();

    EinundzwanzigServer::actingAs($user)
        ->tool(AddMeetupToMineTool::class, ['meetup' => 'Einundzwanzig Dortmund'])
        ->assertOk()
        ->assertSee('hinzugefügt');

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $meetup->id,
        'user_id' => $user->id,
        'is_leader' => false,
    ]);
});

it('lists joined meetups (not only created ones) in my meetups', function () {
    $user = User::factory()->create();
    $joined = Meetup::factory()->create(['name' => 'Einundzwanzig Dortmund']);
    $joined->users()->attach($user->id, ['is_leader' => false]);

    $response = EinundzwanzigServer::actingAs($user)->tool(ListMyMeetupsTool::class);

    $response->assertOk()->assertSee('Einundzwanzig Dortmund');
});

it('makes the creator a leader so the meetup shows in my meetups', function () {
    $user = User::factory()->create();
    City::factory()->create(['name' => 'Ansbach']);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupTool::class, ['name' => 'Einundzwanzig Ansbach', 'city' => 'Ansbach'])
        ->assertOk();

    $meetup = Meetup::query()->where('name', 'Einundzwanzig Ansbach')->sole();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $meetup->id,
        'user_id' => $user->id,
        'is_leader' => true,
    ]);
});

it('lets a member add an event to a joined meetup', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['name' => 'Einundzwanzig Dortmund']);
    $meetup->users()->attach($user->id, ['is_leader' => false]);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupEventTool::class, [
            'meetup' => 'Einundzwanzig Dortmund',
            'start' => '2026-08-01 18:00:00',
        ])
        ->assertOk();

    $this->assertDatabaseHas('meetup_events', [
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
    ]);
});
