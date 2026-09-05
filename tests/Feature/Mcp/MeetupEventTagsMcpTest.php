<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\MeetupEvent\CreateMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\ListMyMeetupEventsTool;
use App\Mcp\Tools\MeetupEvent\ShowMyMeetupEventTool;
use App\Mcp\Tools\MeetupEvent\UpdateMeetupEventTool;
use App\Mcp\Tools\Search\ListEventTagsTool;
use App\Models\Meetup;
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

/*
|--------------------------------------------------------------------------
| Writing tags (issue #117, second half)
|--------------------------------------------------------------------------
|
| create-meetup-event and update-meetup-event take tags BY NAME. Three rules are
| under test throughout and each has its own case below:
|
|   - only a tag Tag::scopeSelectableBy() offers may be attached, the same rule the
|     web picker states ("a crafted request must not attach someone else's unapproved
|     suggestion") -- an MCP call is such a request path;
|   - an unknown or ambiguous name is REFUSED, never created and never half-applied;
|   - on update, an omitted `tags` preserves, `[]` removes, a list replaces.
|
| The tag count is asserted alongside almost every case on purpose. Every spatie write
| path routes unknown strings through Tag::findOrCreate(), so "the right tags are
| attached" and "no tag was invented" are two different claims, and only the second one
| catches the failure mode this feature exists to prevent.
*/

/**
 * @param  array<string, string>  $names  locale => name
 */
function mcpEventTag(array $names): Tag
{
    return Tag::factory()->ofType('meetup_event')->named($names)->create();
}

function mcpEventFor(User $user, array $attributes = []): MeetupEvent
{
    return MeetupEvent::factory()->create([
        'created_by' => $user->id,
        'title' => 'Original',
        'location' => 'Marktplatz',
        ...$attributes,
    ]);
}

/**
 * @return array<int, int>
 */
function tagIdsOf(MeetupEvent $event): array
{
    return $event->fresh()->tags->pluck('id')->sort()->values()->all();
}

it('attaches tags by name when creating a meetup event', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);
    $tag = mcpEventTag(['de' => 'Vortrag', 'en' => 'Talk']);

    EinundzwanzigServer::actingAs($user)->tool(CreateMeetupEventTool::class, [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'tags' => ['Vortrag'],
    ])->assertOk()->assertSee('Vortrag');

    $event = MeetupEvent::query()->where('location', 'Marktplatz')->sole();

    expect(tagIdsOf($event))->toBe([$tag->id])
        // Nothing was invented alongside the match: the one tag in the fixture is
        // still the only tag in the database.
        ->and(Tag::query()->count())->toBe(1);
});

it('gives every occurrence of a created series the same tags', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);
    $tag = mcpEventTag(['de' => 'Stammtisch', 'en' => 'Meetup']);

    EinundzwanzigServer::actingAs($user)->tool(CreateMeetupEventTool::class, [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'recurrence_type' => 'weekly',
        'recurrence_interval' => 1,
        'recurrence_end_date' => '2026-08-22 18:00:00',
        'tags' => ['Meetup'],
    ])->assertOk();

    $events = MeetupEvent::query()->where('meetup_id', $meetup->id)->get();

    expect($events)->toHaveCount(4);

    // A series whose first date carries the selection and whose others do not is the
    // failure the Livewire editor already guards against; the same must hold here.
    $events->each(fn (MeetupEvent $event) => expect(tagIdsOf($event))->toBe([$tag->id]));
});

it('refuses an unknown tag name on create and creates neither event nor tag', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);
    mcpEventTag(['de' => 'Vortrag', 'en' => 'Talk']);

    EinundzwanzigServer::actingAs($user)->tool(CreateMeetupEventTool::class, [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'tags' => ['Vortrag', 'Gaming'],
    ])->assertHasErrors(['Gaming']);

    expect(MeetupEvent::query()->count())->toBe(0)
        ->and(Tag::query()->count())->toBe(1);
});

it('replaces the whole tag set on update', function () {
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    $before = mcpEventTag(['de' => 'Vortrag', 'en' => 'Talk']);
    $after = mcpEventTag(['de' => 'Workshop', 'en' => 'Workshop']);
    $event->syncTagsWithType([$before], 'meetup_event');

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => ['Workshop']])
        ->assertOk()
        ->assertSee('Workshop');

    // Replaced, not merged: the previous tag is gone.
    expect(tagIdsOf($event))->toBe([$after->id]);
});

it('preserves the existing tags when tags is omitted', function () {
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    $tag = mcpEventTag(['de' => 'Vortrag', 'en' => 'Talk']);
    $event->syncTagsWithType([$tag], 'meetup_event');

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'location' => 'Rathaus'])
        ->assertOk();

    expect(tagIdsOf($event))->toBe([$tag->id])
        ->and($event->fresh()->location)->toBe('Rathaus');
});

it('preserves the existing tags when tags is explicitly null', function () {
    /*
     * The trap #70 fell into, on a different field. Laravel's `sometimes` counts an
     * explicitly sent null as PRESENT, so `links: null` reached update() and destroyed
     * a stored list of five labelled entries; nine mutation probes missed it because no
     * test sent that input. MCP has no FormRequest in the path and its JSON schema
     * makes null reachable for any property, so the input is sent here on purpose.
     */
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    $tag = mcpEventTag(['de' => 'Vortrag', 'en' => 'Talk']);
    $event->syncTagsWithType([$tag], 'meetup_event');

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => null])
        ->assertOk();

    expect(tagIdsOf($event))->toBe([$tag->id]);
});

it('removes every tag when tags is an empty list', function () {
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    $tag = mcpEventTag(['de' => 'Vortrag', 'en' => 'Talk']);
    $event->syncTagsWithType([$tag], 'meetup_event');

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => []])
        ->assertOk()
        ->assertSee('"tags":[]');

    expect(tagIdsOf($event))->toBe([])
        // Removing it from the event does not remove it from the vocabulary.
        ->and(Tag::query()->count())->toBe(1);
});

it('leaves every unrelated field alone on a tags-only update', function () {
    $user = User::factory()->create();
    $event = mcpEventFor($user, ['description' => 'Wie immer im Hinterzimmer.']);
    $tag = mcpEventTag(['de' => 'Vortrag', 'en' => 'Talk']);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => ['Talk']])
        ->assertOk();

    $fresh = $event->fresh();

    // Fields that were never sent must read exactly as they did before.
    expect($fresh->title)->toBe('Original')
        ->and($fresh->location)->toBe('Marktplatz')
        ->and($fresh->description)->toBe('Wie immer im Hinterzimmer.')
        ->and($fresh->start->equalTo($event->start))->toBeTrue()
        ->and(tagIdsOf($event))->toBe([$tag->id]);
});

it('refuses an unknown tag name on update and leaves the event untouched', function () {
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    $tag = mcpEventTag(['de' => 'Vortrag', 'en' => 'Talk']);
    $event->syncTagsWithType([$tag], 'meetup_event');

    EinundzwanzigServer::actingAs($user)->tool(UpdateMeetupEventTool::class, [
        'id' => $event->id,
        'location' => 'Rathaus',
        'tags' => ['Vortrag', 'Gaming'],
    ])->assertHasErrors(['Gaming']);

    // Not partially applied: neither the tag that WOULD have resolved nor the field
    // that travelled with it may have landed.
    $fresh = $event->fresh();

    expect($fresh->location)->toBe('Marktplatz')
        ->and(tagIdsOf($event))->toBe([$tag->id])
        ->and(Tag::query()->count())->toBe(1);
});

it('refuses an ambiguous tag name instead of picking the first hit', function () {
    // Two tags carrying the same word in different languages. There is no such
    // collision inside meetup_event today, but a tag is user-editable and the day one
    // appears, silently attaching whichever row came back first is the wrong answer.
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    $german = mcpEventTag(['de' => 'Gaming']);
    $english = mcpEventTag(['en' => 'Gaming']);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => ['Gaming']])
        ->assertHasErrors(['#'.$german->id, '#'.$english->id]);

    expect(tagIdsOf($event))->toBe([]);
});

it('refuses someone elses unapproved suggestion', function () {
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    $stranger = User::factory()->create();

    $suggestion = Tag::factory()
        ->ofType('meetup_event')
        ->named(['de' => 'Geheimtipp'])
        ->pending($stranger)
        ->create();

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => ['Geheimtipp']])
        ->assertHasErrors(['Geheimtipp']);

    expect(tagIdsOf($event))->toBe([])
        // Refused, not created a second time under the caller's own name.
        ->and(Tag::query()->count())->toBe(1)
        ->and($suggestion->fresh()->approved_at)->toBeNull();
});

it('accepts the callers own unapproved suggestion', function () {
    // The other half of scopeSelectableBy(): a suggester can use what they proposed,
    // which is what makes the refusal above about ownership rather than about approval.
    $user = User::factory()->create();
    $event = mcpEventFor($user);

    $ownSuggestion = Tag::factory()
        ->ofType('meetup_event')
        ->named(['de' => 'Geheimtipp'])
        ->pending($user)
        ->create();

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => ['Geheimtipp']])
        ->assertOk();

    expect(tagIdsOf($event))->toBe([$ownSuggestion->id]);
});

it('matches a tag name in any of the nine locales, case-insensitively', function () {
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    $tag = mcpEventTag(['de' => 'Vortrag', 'cs' => 'Přednáška', 'pl' => 'Prelekcja']);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => ['  přednáška ']])
        ->assertOk();

    expect(tagIdsOf($event))->toBe([$tag->id]);
});

it('does not match a tag of another type', function () {
    // Every lookup is scoped to the meetup_event group. The curated vocabulary has 35
    // name collisions and every one of them is across types -- "Einsteiger" exists in
    // meetup_event and in library_item -- so an unscoped lookup would be ambiguous by
    // construction rather than by accident.
    $user = User::factory()->create();
    $event = mcpEventFor($user);
    Tag::factory()->ofType('library_item')->named(['de' => 'Einsteiger'])->create();

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => ['Einsteiger']])
        ->assertHasErrors(['Einsteiger']);

    expect(tagIdsOf($event))->toBe([])
        ->and(Tag::query()->count())->toBe(1);
});

it('refuses to tag someone elses event', function () {
    // The gate is the one the tool already applied; `tags` must not be a way past it.
    $owner = User::factory()->create();
    $event = mcpEventFor($owner);
    mcpEventTag(['de' => 'Vortrag']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateMeetupEventTool::class, ['id' => $event->id, 'tags' => ['Vortrag']])
        ->assertHasErrors();

    expect(tagIdsOf($event))->toBe([]);
});

it('lists the event tag vocabulary with every name a tag carries', function () {
    $user = User::factory()->create();

    $names = [
        'de' => 'Vortrag', 'en' => 'Talk', 'cs' => 'Přednáška', 'es' => 'Charla',
        'hu' => 'Előadás', 'lv' => 'Lekcija', 'nl' => 'Lezing', 'pl' => 'Prelekcja',
        'pt' => 'Palestra',
    ];

    mcpEventTag($names);

    // All nine, so an agent can pick a name in its user's language without guessing --
    // and every one of them is a name update-meetup-event will resolve.
    EinundzwanzigServer::actingAs($user)
        ->tool(ListEventTagsTool::class, [])
        ->assertOk()
        ->assertSee(array_values($names));
});

it('offers only the tags that can actually be attached', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    mcpEventTag(['de' => 'Vortrag']);
    Tag::factory()->ofType('meetup_event')->named(['de' => 'Fremdvorschlag'])->pending($stranger)->create();
    Tag::factory()->ofType('meetup_event')->named(['de' => 'Eigenvorschlag'])->pending($user)->create();
    Tag::factory()->ofType('library_item')->named(['de' => 'Buchtipp'])->create();

    EinundzwanzigServer::actingAs($user)
        ->tool(ListEventTagsTool::class, [])
        ->assertOk()
        ->assertSee(['Vortrag', 'Eigenvorschlag'])
        // A name offered here that the write tools would then refuse is worse than no
        // list at all -- the agent would have no way to tell the two apart.
        ->assertDontSee('Fremdvorschlag')
        ->assertDontSee('Buchtipp');
});
