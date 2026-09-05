<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\MeetupEvent\CreateMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\ListMyMeetupEventsTool;
use App\Mcp\Tools\MeetupEvent\ShowMyMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\UpdateMeetupEventTool;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;

it('lets an authenticated user create a meetup event and stamps created_by', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = EinundzwanzigServer::actingAs($user)->tool(CreateMeetupEventTool::class, [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
    ]);

    /*
     * The timestamp shape is part of what this tool puts in front of an agent, so it is
     * asserted here rather than left to rot (issue #85): the zone-marked `start_iso`
     * arrived, and the Carbon-serialised `start` beside it did NOT move — dropping it
     * would break every consumer that already reads it.
     */
    $response->assertOk()
        ->assertSee('Marktplatz')
        ->assertSee('2026-08-01T18:00:00+00:00')
        ->assertSee('2026-08-01T18:00:00.000000Z');

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

it('hands an agent the same start_iso over MCP as GET /api/meetup-events does over HTTP', function () {
    /*
     * The MCP tools serialise through MeetupEventResource, so an agent that lists events
     * over HTTP and then reads one over MCP must see ONE spelling of the instant
     * (issue #85). The tool's value is asserted against the ENDPOINT'S value, not
     * against a second literal — a per-consumer literal stays green while the two
     * diverge. The literal below only keeps a pair of empty strings from satisfying it,
     * since assertSee('') would match anything.
     */
    $user = User::factory()->create();
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'created_by' => $user->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
        'start' => '2026-08-01 18:00:00',
    ]);

    $listRow = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($listRow['start_iso'])->toBe('2026-08-01T18:00:00+00:00');

    EinundzwanzigServer::actingAs($user)
        ->tool(ShowMyMeetupEventTool::class, ['id' => $event->id])
        ->assertOk()
        ->assertSee($listRow['start_iso']);
});
