<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Venue\CreateVenueTool;
use App\Mcp\Tools\Venue\ListMyVenuesTool;
use App\Mcp\Tools\Venue\ShowMyVenueTool;
use App\Mcp\Tools\Venue\UpdateVenueTool;
use App\Models\City;
use App\Models\User;
use App\Models\Venue;

it('lets an authenticated user create a venue and stamps created_by', function () {
    $user = User::factory()->create();
    $city = City::factory()->create();

    $response = EinundzwanzigServer::actingAs($user)->tool(CreateVenueTool::class, [
        'name' => 'Bitcoin Hub',
        'city_id' => $city->id,
        'street' => 'Satoshi Street 21',
    ]);

    $response->assertOk()->assertSee('Bitcoin Hub');

    $this->assertDatabaseHas('venues', [
        'name' => 'Bitcoin Hub',
        'created_by' => $user->id,
    ]);
});

it('fails validation for missing fields', function () {
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(CreateVenueTool::class, [])
        ->assertHasErrors();
});

it('lets the owner update a venue', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateVenueTool::class, ['id' => $venue->id, 'name' => 'Orange Hub'])
        ->assertOk()
        ->assertSee('Orange Hub');
});

it('forbids updating someone elses venue', function () {
    $owner = User::factory()->create();
    $venue = Venue::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateVenueTool::class, ['id' => $venue->id, 'name' => 'Hijack'])
        ->assertHasErrors();
});

it('returns only own venues in the mine list', function () {
    $user = User::factory()->create();
    Venue::factory()->count(2)->create(['created_by' => $user->id]);
    Venue::factory()->create(['created_by' => User::factory()->create()->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyVenuesTool::class)
        ->assertOk();
});

it('forbids viewing someone elses venue in mine show', function () {
    $owner = User::factory()->create();
    $venue = Venue::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(ShowMyVenueTool::class, ['id' => $venue->id])
        ->assertHasErrors();
});
