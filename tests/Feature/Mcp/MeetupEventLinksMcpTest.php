<?php

/*
|--------------------------------------------------------------------------
| Issue #108 — the MCP tools know about `links`, not only about `link`
|--------------------------------------------------------------------------
|
| The schema is the concrete exposure the issue names: an agent can only send
| what the schema offers, so as long as `link` was the only link field there,
| an agent-driven update was structurally unable to keep a multi-entry list —
| it either re-sent one URL or cleared everything.
|
| Two halves, and both are needed. The schema tests state what an agent can
| SEE; the behaviour tests state what happens when it writes, including the
| clearing shape that produced the data loss.
|
*/

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\MeetupEvent\CreateMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\UpdateMeetupEventTool;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;

/** @return array<string, mixed> */
function mcpToolProperties(string $tool): array
{
    return app($tool)->toArray()['inputSchema']['properties'];
}

/** @return list<array{url: string, label?: string}> */
function threeLabelledLinks(): array
{
    return [
        ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
    ];
}

it('offers links on both meetup event tools, as a list of url and label', function (string $tool) {
    $properties = mcpToolProperties($tool);

    expect($properties)->toHaveKey('links')
        ->and($properties['links']['type'])->toBe('array')
        ->and($properties['links']['items']['properties'])->toHaveKeys(['url', 'label'])
        ->and($properties['links']['items']['required'])->toBe(['url'])
        ->and($properties['links']['maxItems'])->toBe(MeetupEvent::MAX_LINKS);
})->with([CreateMeetupEventTool::class, UpdateMeetupEventTool::class]);

it('keeps the deprecated link in the schema and says what it addresses', function (string $tool) {
    $properties = mcpToolProperties($tool);

    // Kept, not removed: an agent that has been sending `link` for a year keeps
    // working. What changes is that its description now says how far it reaches.
    expect($properties)->toHaveKey('link')
        ->and($properties['link']['description'])->toContain('VERALTET')
        ->and($properties['link']['description'])->toContain('links');
})->with([CreateMeetupEventTool::class, UpdateMeetupEventTool::class]);

it('says on the update tool that link touches the first entry only', function () {
    expect(mcpToolProperties(UpdateMeetupEventTool::class)['link']['description'])
        ->toContain('ERSTEN');
});

it('creates a meetup event with a whole list of links through the tool', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)->tool(CreateMeetupEventTool::class, [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'links' => threeLabelledLinks(),
    ])->assertOk();

    expect(MeetupEvent::query()->latest('id')->firstOrFail()->linkList())->toBe([
        ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
    ]);
});

it('replaces the whole list through the update tool', function () {
    $user = User::factory()->create();
    $event = MeetupEvent::factory()->create(['created_by' => $user->id, 'links' => threeLabelledLinks()]);

    EinundzwanzigServer::actingAs($user)->tool(UpdateMeetupEventTool::class, [
        'id' => $event->id,
        'links' => [['url' => 'https://example.com/only', 'label' => 'Only']],
    ])->assertOk();

    expect($event->fresh()->linkList())->toBe([['url' => 'https://example.com/only', 'label' => 'Only']]);
});

it('does not wipe the list when an agent updates other fields and sends no link at all', function () {
    $user = User::factory()->create();
    $event = MeetupEvent::factory()->create(['created_by' => $user->id, 'links' => threeLabelledLinks()]);

    EinundzwanzigServer::actingAs($user)->tool(UpdateMeetupEventTool::class, [
        'id' => $event->id,
        'location' => 'Rathaus',
    ])->assertOk();

    expect($event->fresh()->linkList())->toHaveCount(3)
        ->and($event->fresh()->location)->toBe('Rathaus');
});

/*
 * The measured defect of the issue, through the door it walked in: an agent that
 * clears the one link field it knows about. Three labelled links, one call — and
 * before #108 all three were gone.
 */
it('removes only the first entry when an agent clears the deprecated link', function () {
    $user = User::factory()->create();
    $event = MeetupEvent::factory()->create(['created_by' => $user->id, 'links' => threeLabelledLinks()]);

    EinundzwanzigServer::actingAs($user)->tool(UpdateMeetupEventTool::class, [
        'id' => $event->id,
        'link' => null,
    ])->assertOk();

    expect($event->fresh()->linkList())->toBe([
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
    ]);
});

it('replaces only the first entry when an agent sends a single link', function () {
    $user = User::factory()->create();
    $event = MeetupEvent::factory()->create(['created_by' => $user->id, 'links' => threeLabelledLinks()]);

    EinundzwanzigServer::actingAs($user)->tool(UpdateMeetupEventTool::class, [
        'id' => $event->id,
        'link' => 'https://example.com/agent',
    ])->assertOk();

    expect($event->fresh()->linkList())->toBe([
        ['url' => 'https://example.com/agent', 'label' => null],
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
    ]);
});
