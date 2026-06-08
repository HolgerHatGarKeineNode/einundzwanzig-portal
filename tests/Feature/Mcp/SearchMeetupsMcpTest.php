<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Search\SearchMeetupsTool;
use App\Models\City;
use App\Models\Meetup;
use App\Models\User;

it('finds an existing meetup by its name', function () {
    Meetup::factory()->create(['name' => 'Einundzwanzig München']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(SearchMeetupsTool::class, ['search' => 'münchen'])
        ->assertOk()
        ->assertSee('Einundzwanzig München');
});

it('finds an existing meetup by its city name', function () {
    $city = City::factory()->create(['name' => 'Nürnberg']);
    Meetup::factory()->create(['name' => 'Bitcoin Treff', 'city_id' => $city->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(SearchMeetupsTool::class, ['search' => 'Nürnberg'])
        ->assertOk()
        ->assertSee('Bitcoin Treff');
});

it('returns no match for an unknown city so a new meetup can be proposed', function () {
    Meetup::factory()->create(['name' => 'Einundzwanzig München']);

    $response = EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(SearchMeetupsTool::class, ['search' => 'Gibtsnichtstadt']);

    $response->assertOk()->assertDontSee('Einundzwanzig München');
});
