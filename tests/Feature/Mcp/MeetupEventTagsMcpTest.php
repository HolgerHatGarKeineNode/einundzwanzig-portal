<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\MeetupEvent\ListMyMeetupEventsTool;
use App\Mcp\Tools\MeetupEvent\ShowMyMeetupEventTool;
use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Models\User;

/*
 * Issue #117: the MCP read tools returned no tag-related field at all.
 *
 * The cause was not a missing field in the resource -- MeetupEventResource has emitted
 * `tags` since #39 -- but that it emits them under whenLoaded(), and neither tool loaded
 * the relation. So the key was ABSENT rather than empty, which is worse than either:
 * a caller cannot tell "this event has no tags" from "this endpoint does not do tags".
 *
 * These tests therefore assert the tag's name is on the wire, not merely that a `tags`
 * key exists -- an empty array would satisfy the weaker assertion while the defect stood.
 */
function taggedEventFor(User $user): MeetupEvent
{
    $tag = Tag::findOrCreate('Vortrag', 'meetup_event');
    $tag->forceFill(['approved_at' => now()])->save();

    $event = MeetupEvent::factory()->create(['created_by' => $user->id]);
    $event->syncTagsWithType([$tag], 'meetup_event');

    return $event;
}

it('returns the tags of an event from show-my-meetup-event', function () {
    $user = User::factory()->create();
    $event = taggedEventFor($user);

    EinundzwanzigServer::actingAs($user)
        ->tool(ShowMyMeetupEventTool::class, ['id' => $event->id])
        ->assertOk()
        ->assertSee('Vortrag');
});

it('returns the tags of an event from list-my-meetup-events', function () {
    $user = User::factory()->create();
    taggedEventFor($user);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyMeetupEventsTool::class, [])
        ->assertOk()
        ->assertSee('Vortrag');
});

it('lists an untagged event with an empty tag list rather than no tag field', function () {
    // The distinction the defect erased: an event without tags must still say so.
    $user = User::factory()->create();
    MeetupEvent::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyMeetupEventsTool::class, [])
        ->assertOk()
        ->assertSee('"tags":[]');
});
