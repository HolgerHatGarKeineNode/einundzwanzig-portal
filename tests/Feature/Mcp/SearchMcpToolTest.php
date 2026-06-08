<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Search\ListCountriesTool;
use App\Mcp\Tools\Search\SearchCitiesTool;
use App\Mcp\Tools\Search\SearchCoursesTool;
use App\Mcp\Tools\Search\SearchLecturersTool;
use App\Mcp\Tools\Search\SearchVenuesTool;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\User;
use App\Models\Venue;

/**
 * NOTE: The search closures use Postgres `ilike`, which the SQLite test database
 * does not support. We therefore call the tools without a `search` argument and
 * rely on the limit(10) branch to return the single seeded record.
 */
it('returns cities', function () {
    City::factory()->create(['name' => 'Ansbach']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(SearchCitiesTool::class, [])
        ->assertOk()
        ->assertSee('Ansbach');
});

it('returns venues', function () {
    Venue::factory()->create(['name' => 'Plan B Lugano']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(SearchVenuesTool::class, [])
        ->assertOk()
        ->assertSee('Plan B Lugano');
});

it('returns lecturers', function () {
    Lecturer::factory()->create(['name' => 'Saifedean Ammous']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(SearchLecturersTool::class, [])
        ->assertOk()
        ->assertSee('Saifedean Ammous');
});

it('returns courses', function () {
    Course::factory()->create(['name' => 'Bitcoin Masterclass']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(SearchCoursesTool::class, [])
        ->assertOk()
        ->assertSee('Bitcoin Masterclass');
});

it('filters courses by user_id', function () {
    $user = User::factory()->create();
    Course::factory()->create(['name' => 'Owned Course', 'created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(SearchCoursesTool::class, ['user_id' => $user->id])
        ->assertOk()
        ->assertSee('Owned Course');
});

it('lists countries', function () {
    Country::factory()->create(['name' => 'Deutschland', 'code' => 'DE']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(ListCountriesTool::class, [])
        ->assertOk();
});
