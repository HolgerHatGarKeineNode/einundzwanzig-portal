<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Meetup\CreateMeetupTool;
use App\Mcp\Tools\Meetup\UpdateMeetupTool;
use App\Mcp\Tools\MeetupEvent\CreateMeetupEventTool;
use App\Models\City;
use App\Models\Meetup;
use App\Models\User;

it('creates a meetup event by meetup name instead of id', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'name' => 'Einundzwanzig Ansbach']);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupEventTool::class, [
            'meetup' => 'Einundzwanzig Ansbach',
            'start' => '2026-08-01 18:00:00',
        ])
        ->assertOk();

    $this->assertDatabaseHas('meetup_events', [
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
    ]);
});

it('returns the list of own meetups when the meetup name is unknown', function () {
    $user = User::factory()->create();
    Meetup::factory()->create(['created_by' => $user->id, 'name' => 'Einundzwanzig Ansbach']);
    Meetup::factory()->create(['created_by' => $user->id, 'name' => 'Plan B Lugano']);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupEventTool::class, [
            'meetup' => 'Gibt es nicht',
            'start' => '2026-08-01 18:00:00',
        ])
        ->assertHasErrors()
        ->assertSee('Einundzwanzig Ansbach')
        ->assertSee('Plan B Lugano');
});

it('matches the meetup name case-insensitively', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'name' => 'Einundzwanzig Ansbach']);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupEventTool::class, [
            'meetup' => 'einundzwanzig ansbach',
            'start' => '2026-08-01 18:00:00',
        ])
        ->assertOk();

    $this->assertDatabaseHas('meetup_events', ['meetup_id' => $meetup->id]);
});

it('updates a meetup selected by name', function () {
    $user = User::factory()->create();
    Meetup::factory()->create(['created_by' => $user->id, 'name' => 'Altname']);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupTool::class, ['meetup' => 'Altname', 'name' => 'Neuname'])
        ->assertOk()
        ->assertSee('Neuname');

    $this->assertDatabaseHas('meetups', ['name' => 'Neuname', 'created_by' => $user->id]);
});

it('creates a meetup resolving the city by name', function () {
    $user = User::factory()->create();
    $city = City::factory()->create(['name' => 'Ansbach']);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateMeetupTool::class, ['name' => 'Einundzwanzig Ansbach', 'city' => 'Ansbach'])
        ->assertOk();

    $this->assertDatabaseHas('meetups', [
        'name' => 'Einundzwanzig Ansbach',
        'city_id' => $city->id,
        'created_by' => $user->id,
    ]);
});

it('reports an unknown city name when creating a meetup', function () {
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(CreateMeetupTool::class, ['name' => 'Test', 'city' => 'Gibtsnicht'])
        ->assertHasErrors()
        ->assertSee('nicht gefunden');
});
