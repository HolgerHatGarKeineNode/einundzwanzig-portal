<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\City\CreateCityTool;
use App\Mcp\Tools\City\ListMyCitiesTool;
use App\Mcp\Tools\City\ShowMyCityTool;
use App\Mcp\Tools\City\UpdateCityTool;
use App\Models\City;
use App\Models\Country;
use App\Models\User;

it('lets an authenticated user create a city and stamps created_by', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();

    $response = EinundzwanzigServer::actingAs($user)->tool(CreateCityTool::class, [
        'name' => 'Ansbach',
        'country_id' => $country->id,
        'longitude' => 10.5806,
        'latitude' => 49.3034,
    ]);

    $response->assertOk()->assertSee('Ansbach');

    $this->assertDatabaseHas('cities', [
        'name' => 'Ansbach',
        'created_by' => $user->id,
    ]);
});

it('fails validation for missing fields', function () {
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(CreateCityTool::class, [])
        ->assertHasErrors();
});

it('lets the owner update a city', function () {
    $user = User::factory()->create();
    $city = City::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateCityTool::class, ['id' => $city->id, 'name' => 'Nürnberg'])
        ->assertOk()
        ->assertSee('Nürnberg');
});

it('forbids updating someone elses city', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateCityTool::class, ['id' => $city->id, 'name' => 'Hijack'])
        ->assertHasErrors();
});

it('returns only own cities in the mine list', function () {
    $user = User::factory()->create();
    City::factory()->count(2)->create(['created_by' => $user->id]);
    City::factory()->create(['created_by' => User::factory()->create()->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyCitiesTool::class)
        ->assertOk();
});

it('forbids viewing someone elses city in mine show', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(ShowMyCityTool::class, ['id' => $city->id])
        ->assertHasErrors();
});
