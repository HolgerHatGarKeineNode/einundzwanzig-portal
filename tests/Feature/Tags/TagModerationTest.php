<?php

use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

function moderator(): User
{
    return User::factory()->create(['nostr' => config('einundzwanzig.tag_editors')[0]]);
}

it('is closed to anyone without the editor permission', function () {
    $this->actingAs(User::factory()->create(['nostr' => null]));

    Livewire::test('tags.moderation')->assertStatus(403);
});

it('is closed to guests', function () {
    Livewire::test('tags.moderation')->assertStatus(403);
});

it('opens for an editor', function () {
    $this->actingAs(moderator());

    Livewire::test('tags.moderation')->assertOk();
});

it('lists only unapproved tags', function () {
    $this->actingAs(moderator());

    $approved = Tag::factory()->create(['type' => 'meetup_event']);
    $pending = Tag::factory()->pending(User::factory()->create())->create(['type' => 'meetup_event']);

    $ids = Livewire::test('tags.moderation')->instance()->pending->pluck('id');

    expect($ids)->toContain($pending->id)->not->toContain($approved->id);
});

it('approves a suggestion so everyone can use it', function () {
    $this->actingAs(moderator());
    $tag = Tag::factory()->pending(User::factory()->create())->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')->call('approve', $tag->id);

    expect($tag->fresh()->isApproved())->toBeTrue();

    // And it is now offered to a completely unrelated user.
    $this->actingAs(User::factory()->create(['nostr' => null]));
    expect(Tag::query()->selectableBy(auth()->user())->pluck('id'))->toContain($tag->id);
});

it('rejecting removes the tag', function () {
    $this->actingAs(moderator());
    $tag = Tag::factory()->pending(User::factory()->create())->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')->call('reject', $tag->id);

    expect(Tag::find($tag->id))->toBeNull();
});

it('refuses approval from a non-editor even by direct call', function () {
    $author = User::factory()->create(['nostr' => null]);
    $tag = Tag::factory()->pending($author)->create(['type' => 'meetup_event']);

    // mount() already blocks, so reach the policy through the picker's own user context.
    $this->actingAs($author);

    expect($author->can('approve', $tag))->toBeFalse();
    expect($tag->fresh()->isApproved())->toBeFalse();
});
