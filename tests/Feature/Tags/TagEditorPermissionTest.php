<?php

use App\Models\Tag;
use App\Models\User;
use App\Support\TagEditorGate;
use swentel\nostr\Key\Key;

/**
 * The first configured board npub, used as "a real editor" throughout.
 */
function editorNpub(): string
{
    return config('einundzwanzig.tag_editors')[0];
}

function editorUser(): User
{
    return User::factory()->create(['nostr' => editorNpub()]);
}

/*
|--------------------------------------------------------------------------
| The gate itself, and the workflow it gates
|--------------------------------------------------------------------------
|
| TagEditorGate is unaffected by einundzwanzig.tags.require_approval — it only
| ever answers "is this npub an editor". The TagPolicy cases below are not:
| with the flag at its shipped default (false, issue #143) everyone creates
| outright and nobody but an editor edits, so each case that describes the
| approval workflow turns the flag ON for itself. The workflow is dormant, not
| deleted, and these are what keep it proven.
|
*/

beforeEach(function () {
    TagEditorGate::flush();
});

it('ships the board npubs as editors', function () {
    $editors = config('einundzwanzig.tag_editors');

    expect($editors)->toHaveCount(7);

    foreach ($editors as $npub) {
        expect($npub)->toStartWith('npub1')->toHaveLength(63);
    }
});

it('recognises a configured editor by npub', function () {
    expect(TagEditorGate::allows(editorUser()))->toBeTrue();
});

it('recognises a configured editor by hex pubkey', function () {
    $hex = (new Key)->convertToHex(editorNpub());

    $user = User::factory()->create(['nostr' => $hex]);

    expect(TagEditorGate::allows($user))->toBeTrue();
});

it('rejects an unknown npub', function () {
    $user = User::factory()->create([
        'nostr' => 'npub1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq',
    ]);

    expect(TagEditorGate::allows($user))->toBeFalse();
});

it('rejects a user without any nostr identity', function () {
    expect(TagEditorGate::allows(User::factory()->create(['nostr' => null])))->toBeFalse()
        ->and(TagEditorGate::allows(User::factory()->create(['nostr' => ''])))->toBeFalse()
        ->and(TagEditorGate::allows(null))->toBeFalse();
});

it('denies everyone when the editor list is empty', function () {
    // Build the would-be editor first — after this the config no longer knows them.
    $user = editorUser();

    // Fail-closed: a missing or emptied config must lock the door, not open it.
    config()->set('einundzwanzig.tag_editors', []);
    TagEditorGate::flush();

    expect(TagEditorGate::allows($user))->toBeFalse()
        ->and(TagEditorGate::npubs())->toBe([]);
});

it('drops malformed entries instead of passing them through', function () {
    config()->set('einundzwanzig.tag_editors', ['not-an-npub', '', 'npub1invalid']);
    TagEditorGate::flush();

    expect(TagEditorGate::pubkeys())->toBe([])
        ->and(TagEditorGate::containsNpub('not-an-npub'))->toBeTrue()
        ->and(TagEditorGate::containsPubkey('not-an-npub'))->toBeFalse();
});

it('lets an editor create a tag outright', function () {
    expect(editorUser()->can('create', Tag::class))->toBeTrue();
});

it('does not let a normal user create a tag outright while the gate is on', function () {
    config(['einundzwanzig.tags.require_approval' => true]);

    $user = User::factory()->create(['nostr' => null]);

    expect($user->can('create', Tag::class))->toBeFalse();
});

it('lets a normal user create a tag outright while the gate is off', function () {
    // The shipped default (issue #143). No config() call on purpose: the state
    // production runs in has to be what an unconfigured test sees.
    $user = User::factory()->create(['nostr' => null]);

    expect(config('einundzwanzig.tags.require_approval'))->toBeFalse()
        ->and($user->can('create', Tag::class))->toBeTrue();
});

it('falls back to the closed gate when the config key is missing', function () {
    // Fail-closed, the same direction TagEditorGate states for itself.
    config(['einundzwanzig.tags' => []]);

    $user = User::factory()->create(['nostr' => null]);
    $tag = Tag::factory()->pending($user)->create();

    expect($user->can('create', Tag::class))->toBeFalse()
        ->and(Tag::query()->selectableBy(User::factory()->create())->pluck('id'))
        ->not->toContain($tag->id);
});

it('lets any signed-in user suggest a tag', function () {
    $user = User::factory()->create(['nostr' => null]);

    expect($user->can('suggest', Tag::class))->toBeTrue()
        ->and(editorUser()->can('suggest', Tag::class))->toBeTrue();
});

it('only lets an editor approve a pending tag', function () {
    $author = User::factory()->create(['nostr' => null]);
    $tag = Tag::factory()->pending($author)->create();

    expect($author->can('approve', $tag))->toBeFalse()
        ->and(editorUser()->can('approve', $tag))->toBeTrue();
});

it('lets a suggester fix their own tag until it is approved while the gate is on', function () {
    config(['einundzwanzig.tags.require_approval' => true]);

    $author = User::factory()->create(['nostr' => null]);
    $stranger = User::factory()->create(['nostr' => null]);

    $tag = Tag::factory()->pending($author)->create();

    expect($author->can('update', $tag))->toBeTrue()
        ->and($stranger->can('update', $tag))->toBeFalse();

    $tag->approve();

    // Once approved the tag belongs to the taxonomy, not to its proposer.
    expect($author->can('update', $tag->fresh()))->toBeFalse()
        ->and(editorUser()->can('update', $tag->fresh()))->toBeTrue();
});

it('reserves editing and deleting to editors while the gate is off', function () {
    /*
     * The deliberate tightening of #143. With the gate off there is no state in which
     * a tag is visible to its proposer alone, so the "fix your own suggestion" clause
     * would only hand the proposer of a historic approved_at=null row the right to
     * rename or delete a label other organisers have since attached to their events —
     * and approved_at is not backfilled, so those rows persist.
     */
    $author = User::factory()->create(['nostr' => null]);
    $tag = Tag::factory()->pending($author)->create();

    expect($author->can('update', $tag))->toBeFalse()
        ->and($author->can('delete', $tag))->toBeFalse()
        ->and(editorUser()->can('update', $tag))->toBeTrue()
        ->and(editorUser()->can('delete', $tag))->toBeTrue();
});

it('shows every tag to everyone while the gate is off', function () {
    $author = User::factory()->create(['nostr' => null]);
    $stranger = User::factory()->create(['nostr' => null]);

    $pending = Tag::factory()->pending($author)->create();

    expect($stranger->can('view', $pending))->toBeTrue();

    config(['einundzwanzig.tags.require_approval' => true]);

    expect($stranger->can('view', $pending))->toBeFalse()
        ->and($author->can('view', $pending))->toBeTrue();
});
