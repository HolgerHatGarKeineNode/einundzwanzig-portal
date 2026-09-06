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
use Laravel\Sanctum\Sanctum;

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

it('hands an agent the same series end and record timestamps over MCP as the HTTP resource does', function () {
    /*
     * Issue #125. The MCP tools are the agent-driven consumer of MeetupEventResource,
     * and `recurrence_end_date`, `created_at` and `updated_at` reached them in the
     * `.000000Z` form long after #85 had converged `start` / `end`.
     *
     * Every value is asserted against what the HTTP endpoint returned for the SAME
     * event, not against a second literal — a per-consumer literal stays green while
     * the two diverge. The literals are pinned once on the HTTP side first, so a set
     * of empty strings cannot satisfy assertSee().
     */
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'created_by' => $user->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
        'start' => '2026-08-01 18:00:00',
        'recurrence_end_date' => '2026-12-31 22:59:59',
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-02-03 04:05:06',
    ]);

    $http = $this->getJson("/api/my-meetup-events/{$event->id}")->assertOk()->json('data');

    expect($http['recurrence_end_date_iso'])->toBe('2026-12-31T22:59:59+00:00')
        ->and($http['created_at_iso'])->toBe('2026-01-02T03:04:05+00:00')
        ->and($http['updated_at_iso'])->toBe('2026-02-03T04:05:06+00:00');

    $response = EinundzwanzigServer::actingAs($user)
        ->tool(ShowMyMeetupEventTool::class, ['id' => $event->id])
        ->assertOk()
        ->assertSee($http['recurrence_end_date_iso'])
        ->assertSee($http['created_at_iso'])
        ->assertSee($http['updated_at_iso']);

    // The deprecated spellings did NOT move: dropping them is the breaking change #125
    // deliberately does not make.
    $response->assertSee($http['recurrence_end_date'])
        ->assertSee($http['created_at'])
        ->assertSee($http['updated_at']);
});
