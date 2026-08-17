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

it('does not let a normal user create a tag outright', function () {
    $user = User::factory()->create(['nostr' => null]);

    expect($user->can('create', Tag::class))->toBeFalse();
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

it('lets a suggester fix their own tag until it is approved', function () {
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
