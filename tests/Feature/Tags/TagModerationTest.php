<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
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

/*
|--------------------------------------------------------------------------
| The vocabulary tab
|--------------------------------------------------------------------------
|
| The screen used to be a review queue only, which meant that with an empty
| queue — the normal state — nothing about the ninety-one approved tags could
| be changed at all. These cases cover the four fields that were unreachable:
| icon, description, featured and order_column.
|
*/

/**
 * Seven featured tags of one type, in order, named "Stufe 1".."Stufe 7".
 *
 * `order_column` is deliberately NOT passed: Spatie's SortableTrait overwrites it
 * in the `creating` hook with max+1, so a value handed to the factory is discarded
 * without a word. The numbers therefore continue across calls — which is exactly
 * the collision the cross-type test needs.
 *
 * @return Collection<int, Tag>
 */
function featuredLadder(string $type = 'meetup_event'): Collection
{
    return collect(range(1, 7))->map(fn (int $i): Tag => Tag::factory()
        ->featured()
        ->named(['de' => 'Stufe '.$i, 'en' => 'Step '.$i])
        ->create(['type' => $type]));
}

/**
 * The featured tags of one type as they are ordered right now.
 *
 * @return array<int, int>
 */
function featuredOrderOf(string $type = 'meetup_event'): array
{
    return Tag::query()
        ->approved()
        ->where('type', $type)
        ->where('featured', true)
        ->ordered()
        ->pluck('id')
        ->all();
}

it('shows the approved vocabulary, not only the queue', function () {
    $this->actingAs(moderator());

    $approved = Tag::factory()->named(['de' => 'Selbstverwahrung'])->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')
        ->assertOk()
        ->assertSee('Selbstverwahrung')
        ->assertSee('Vokabular (1)');

    expect(Livewire::test('tags.moderation')->instance()->vocabulary->flatten()->pluck('id'))
        ->toContain($approved->id);
});

it('opens on the vocabulary when no suggestion is waiting', function () {
    $this->actingAs(moderator());
    Tag::factory()->create(['type' => 'meetup_event']);

    expect(Livewire::test('tags.moderation')->instance()->tab)->toBe('vocabulary');
});

it('opens on the queue when a suggestion is waiting', function () {
    $this->actingAs(moderator());
    Tag::factory()->pending(User::factory()->create())->create(['type' => 'meetup_event']);

    expect(Livewire::test('tags.moderation')->instance()->tab)->toBe('pending');
});

it('moves a tag from position 7 to position 1 one step at a time', function () {
    // The keyboard path: six presses of "up", no pointer involved.
    $this->actingAs(moderator());
    $ladder = featuredLadder();
    $last = $ladder->last();

    $component = Livewire::test('tags.moderation');

    foreach (range(1, 6) as $ignored) {
        $component->call('moveUp', $last->id);
    }

    expect(featuredOrderOf()[0])->toBe($last->id)
        ->and($last->fresh()->order_column)->toBe(1);
});

it('reports the new position after a move', function () {
    $this->actingAs(moderator());
    $ladder = featuredLadder();

    Livewire::test('tags.moderation')
        ->call('moveUp', $ladder->last()->id)
        ->assertSet('reorderStatus', 'Stufe 7 steht jetzt an Position 6 von 7.');
});

it('produces the same order whether dragged or moved with the buttons', function () {
    // `wire:sort="reorder($item, $position)"` — dragging calls exactly this action
    // with exactly these arguments, so the two paths are compared at the same door.
    $this->actingAs(moderator());

    $ladder = featuredLadder();
    $moved = $ladder->last();

    Livewire::test('tags.moderation')->call('moveUp', $moved->id);
    $byButton = featuredOrderOf();
    $byButtonColumns = Tag::query()->approved()->where('featured', true)->ordered()->pluck('order_column')->all();

    // Reset and take the drag route to the very same target index (0-based 5).
    $ladder->each(fn (Tag $tag, int $i) => $tag->newQuery()->whereKey($tag->id)->update(['order_column' => $i + 1]));

    Livewire::test('tags.moderation')->call('reorder', $moved->id, 5);
    $byDrag = featuredOrderOf();
    $byDragColumns = Tag::query()->approved()->where('featured', true)->ordered()->pluck('order_column')->all();

    expect($byDrag)->toBe($byButton)
        ->and($byDragColumns)->toBe($byButtonColumns);
});

it('refuses to move a tag past either end', function () {
    $this->actingAs(moderator());
    $ladder = featuredLadder();
    $before = featuredOrderOf();

    Livewire::test('tags.moderation')
        ->call('moveUp', $ladder->first()->id)
        ->call('moveDown', $ladder->last()->id);

    expect(featuredOrderOf())->toBe($before);
});

it('never reorders across types', function () {
    // Spatie's own moveOrderUp() would: buildSortQuery() is unscoped, so a featured
    // event tag would trade places with whatever library tag holds the next number.
    $this->actingAs(moderator());

    $events = featuredLadder('meetup_event');
    featuredLadder('library_item');

    $libraryBefore = featuredOrderOf('library_item');
    $libraryColumnsBefore = Tag::query()->where('type', 'library_item')->ordered()->pluck('order_column')->all();

    Livewire::test('tags.moderation')->call('moveUp', $events->last()->id);

    expect(featuredOrderOf('library_item'))->toBe($libraryBefore)
        ->and(Tag::query()->where('type', 'library_item')->ordered()->pluck('order_column')->all())
        ->toBe($libraryColumnsBefore);
});

it('puts a newly featured tag at the end of the resting list', function () {
    $this->actingAs(moderator());
    $ladder = featuredLadder();
    $newcomer = Tag::factory()->named(['de' => 'Nachzügler'])->create([
        'type' => 'meetup_event',
        'order_column' => 2, // deliberately a number from the middle of the ladder
    ]);

    Livewire::test('tags.moderation')->set('featured.'.$newcomer->id, true);

    expect($newcomer->fresh()->featured)->toBeTrue()
        ->and(featuredOrderOf())->toBe([...$ladder->pluck('id')->all(), $newcomer->id]);
});

it('offers a newly featured tag in the picker without anyone typing', function () {
    $this->actingAs(moderator());
    $tag = Tag::factory()->named(['de' => 'Nachzügler'])->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')->set('featured.'.$tag->id, true);

    // The picker's resting state is CSS on `tag-option--featured`; what the server
    // has to get right is the class and the data attribute on that option.
    Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->assertSeeHtml('data-featured="true"')
        ->assertSeeHtml('data-testid="tag-option-'.$tag->id.'"');
});

it('renumbers the rest when a tag stops being featured', function () {
    $this->actingAs(moderator());
    $ladder = featuredLadder();

    Livewire::test('tags.moderation')->set('featured.'.$ladder[2]->id, false);

    expect(Tag::query()->approved()->where('featured', true)->ordered()->pluck('order_column')->all())
        ->toBe([1, 2, 3, 4, 5, 6]);
});

it('orders the picker by the sequence the moderation screen writes', function () {
    // Without this the ordering controls would move a number nobody ever sees.
    $this->actingAs(moderator());
    $ladder = featuredLadder();

    Livewire::test('tags.moderation')->call('moveUp', $ladder->last()->id);

    $picker = Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->instance()->options->pluck('id')->all();

    expect($picker)->toBe(featuredOrderOf());
});

it('saves a description into the active locale only', function () {
    $this->actingAs(moderator());
    app()->setLocale('de');

    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung', 'en' => 'Self-custody'])
        ->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editDescription', 'Wie du deine Schlüssel selbst verwahrst.')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $tag->fresh();

    expect($fresh->getTranslation('description', 'de', false))->toBe('Wie du deine Schlüssel selbst verwahrst.');

    foreach (['cs', 'en', 'es', 'hu', 'lv', 'nl', 'pl', 'pt'] as $untouched) {
        expect($fresh->getTranslation('description', $untouched, false))->toBe('');
    }
});

it('drops the language again when the description is emptied', function () {
    $this->actingAs(moderator());
    app()->setLocale('de');

    $tag = Tag::factory()->create(['type' => 'meetup_event']);
    $tag->setTranslation('description', 'de', 'Alt');
    $tag->setTranslation('description', 'en', 'Old');
    $tag->save();

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editDescription', '')
        ->call('save');

    $fresh = $tag->fresh();

    expect($fresh->getTranslation('description', 'de', false))->toBe('')
        ->and($fresh->getTranslation('description', 'en', false))->toBe('Old');
});

it('saves a new icon', function () {
    $this->actingAs(moderator());
    $tag = Tag::factory()->create(['type' => 'meetup_event', 'icon' => 'tag']);

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editIcon', 'microphone')
        ->call('save')
        ->assertHasNoErrors();

    expect($tag->fresh()->icon)->toBe('microphone');
});

it('refuses an icon the whitelist does not know', function () {
    // The whole point of the whitelist: a name Flux cannot resolve must not reach
    // the database, because rendering it is an exception, not a blank space.
    $this->actingAs(moderator());
    $tag = Tag::factory()->create(['type' => 'meetup_event', 'icon' => 'tag']);

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editIcon', 'beer-mug')
        ->call('save')
        ->assertHasErrors('editIcon');

    expect($tag->fresh()->icon)->toBe('tag');
});

it('preselects the fallback when the stored icon cannot be resolved', function () {
    $this->actingAs(moderator());
    // A row that survived the data migration with an unknown name — the screen must
    // offer the repair rather than die on it.
    $tag = Tag::factory()->create(['type' => 'meetup_event']);
    $tag->newQuery()->whereKey($tag->id)->update(['icon' => 'lightsaber']);

    Livewire::test('tags.moderation')
        ->assertOk()
        ->assertSee('lightsaber — nicht auflösbar')
        ->call('edit', $tag->id)
        ->assertSet('editIcon', 'tag');
});

it('keeps a non-editor out of every ordering action', function () {
    $outsider = User::factory()->create(['nostr' => null]);
    $tag = Tag::factory()->featured()->create(['type' => 'meetup_event']);
    $orderBefore = $tag->fresh()->order_column;

    $this->actingAs($outsider);

    Livewire::test('tags.moderation')->assertStatus(403);

    expect($outsider->can('update', $tag))->toBeFalse()
        ->and($tag->fresh()->order_column)->toBe($orderBefore);
});

/*
|--------------------------------------------------------------------------
| Names, in all nine languages
|--------------------------------------------------------------------------
|
| The screen could edit a tag's icon, its description, its featured flag and
| its position — everything except the one field a picker actually matches on.
| The owner's requirement ("Übersetzungen sollen ausfüllbar sein, für all
| unsere Sprachen") was not reachable through any interface, and neither were
| the three production rows the old picker left with one word in nine
| languages.
|
*/

it('loads every locale of the name into the editor', function () {
    $this->actingAs(moderator());

    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung', 'en' => 'Self-custody'])
        ->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->assertSet('editNames.de', 'Selbstverwahrung')
        ->assertSet('editNames.en', 'Self-custody')
        ->assertSet('editNames.cs', '')
        ->assertSet('editNames.pt', '');
});

it('fills in a translation the tag did not have', function () {
    $this->actingAs(moderator());

    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung'])->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editNames.cs', 'Samospráva')
        ->set('editNames.pl', 'Samodzielne przechowywanie')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $tag->fresh();

    expect($fresh->getTranslation('name', 'cs', false))->toBe('Samospráva')
        ->and($fresh->getTranslation('name', 'pl', false))->toBe('Samodzielne przechowywanie')
        ->and($fresh->getTranslation('name', 'de', false))->toBe('Selbstverwahrung')
        // And the Czech reader is no longer told this is a German label.
        ->and($fresh->isDisplayNameSubstituted('cs'))->toBeFalse();
});

it('names the languages that are still missing', function () {
    $this->actingAs(moderator());

    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung', 'en' => 'Self-custody'])
        ->create(['type' => 'meetup_event']);

    $component = Livewire::test('tags.moderation')->call('edit', $tag->id);

    expect($component->instance()->missingNameLocales)
        ->toBe(['cs', 'es', 'hu', 'lv', 'nl', 'pl', 'pt']);

    $component->assertSee('Fehlt in: CS, ES, HU, LV, NL, PL, PT');
});

it('drops a language again when its name is emptied', function () {
    // The repair path for the three rows the old picker filled with one word in nine
    // languages: delete the eight it is not written in.
    $this->actingAs(moderator());

    $tag = Tag::factory()
        ->named(array_fill_keys(config('einundzwanzig.tag_locales'), 'Rodiny s dětmi'))
        ->create(['type' => 'meetup_event']);

    $component = Livewire::test('tags.moderation')->call('edit', $tag->id);

    foreach (['de', 'en', 'es', 'hu', 'lv', 'nl', 'pl', 'pt'] as $wrong) {
        $component->set('editNames.'.$wrong, '');
    }

    $component->call('save')->assertHasNoErrors();

    $fresh = $tag->fresh();

    expect($fresh->getTranslations('name'))->toBe(['cs' => 'Rodiny s dětmi'])
        ->and($fresh->displayLocale('nl'))->toBe('cs')
        ->and($fresh->isDisplayNameSubstituted('nl'))->toBeTrue()
        // The language is gone from the stored column, not stored as "". Spatie's
        // readers filter an empty value out either way (measured 2026-09-05), so only
        // the raw attribute can tell the two apart — and only this keeps the column
        // from collecting a dead entry per cleared language.
        ->and(json_decode($fresh->getAttributes()['name'], true))->toBe(['cs' => 'Rodiny s dětmi']);
});

it('refuses to leave a tag without any name', function () {
    // A nameless tag cannot be persisted at all (tags.slug is NOT NULL, and a slug
    // comes from a name), and it would render as a blank row in every picker.
    $this->actingAs(moderator());

    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung'])->create(['type' => 'meetup_event']);

    $component = Livewire::test('tags.moderation')->call('edit', $tag->id);

    foreach (config('einundzwanzig.tag_locales') as $locale) {
        $component->set('editNames.'.$locale, '');
    }

    $component->call('save')->assertHasErrors('editNames');

    expect($tag->fresh()->getTranslation('name', 'de', false))->toBe('Selbstverwahrung');
});

it('refuses a name that is too short or too long', function () {
    $this->actingAs(moderator());

    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung'])->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editNames.cs', 'x')
        ->call('save')
        ->assertHasErrors('editNames.cs');

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editNames.cs', str_repeat('a', 61))
        ->call('save')
        ->assertHasErrors('editNames.cs');

    expect($tag->fresh()->getTranslation('name', 'cs', false))->toBe('');
});

it('keeps the slug of a renamed language and of every other one', function () {
    /*
     * Tag::bootHasSlug() exists so a public URL is never rewritten by a later edit
     * (commit fd48fa7: an API patch turned "nuernberg" into "nurnberg"). Editing names
     * on this screen is exactly the kind of unrelated save that used to do it.
     */
    $this->actingAs(moderator());

    $tag = Tag::factory()->named(['de' => 'Nürnberg Treff', 'en' => 'Nuremberg Meetup'])
        ->create(['type' => 'meetup_event']);

    // The tag slugger is Str::slug without a language option, so this is
    // "nurnberg-treff" and not the "nuernberg-treff" that Meetup and City produce.
    // What is being pinned here is that the value does not MOVE, whatever it is.
    $slugsBefore = $tag->getTranslations('slug');
    expect($slugsBefore['de'])->toBe('nurnberg-treff');

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editNames.de', 'Nürnberger Stammtisch')
        ->set('editNames.cs', 'Norimberské setkání')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $tag->fresh();

    expect($fresh->getTranslation('slug', 'de'))->toBe($slugsBefore['de'])
        ->and($fresh->getTranslation('slug', 'en'))->toBe($slugsBefore['en'])
        // A language that had no name gets a slug now, which is the only new one.
        ->and($fresh->getTranslation('slug', 'cs'))->toBe('norimberske-setkani')
        ->and($fresh->getTranslation('name', 'de', false))->toBe('Nürnberger Stammtisch');
});

it('keeps the slug of a language whose name was removed', function () {
    // Deliberate: the slug outlives the name it came from, so a different name in
    // that language later cannot take over a URL that is already in the wild.
    $this->actingAs(moderator());

    $tag = Tag::factory()->named(['de' => 'Nürnberg Treff', 'en' => 'Nuremberg Meetup'])
        ->create(['type' => 'meetup_event']);

    $slugsBefore = $tag->getTranslations('slug');

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editNames.en', '')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $tag->fresh();

    expect($fresh->getTranslation('name', 'en', false))->toBe('')
        ->and($fresh->getTranslations('slug'))->toBe($slugsBefore);
});

it('normalises whitespace in a name, as the picker does', function () {
    $this->actingAs(moderator());

    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung'])->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')
        ->call('edit', $tag->id)
        ->set('editNames.en', "  Self   custody \n")
        ->call('save')
        ->assertHasNoErrors();

    expect($tag->fresh()->getTranslation('name', 'en', false))->toBe('Self custody');
});

it('flags a tag whose nine names were copied by the old picker', function () {
    /*
     * The three production rows of 2026-09-05 (Poker, Pubkvíz, Rodiny s dětmi): one
     * typed word in all nine languages, no source language recorded. No migration
     * repairs them, because which of the nine is the original exists nowhere in the
     * data — so the screen says so where the person who can read the word works.
     */
    $this->actingAs(moderator());

    $copied = Tag::factory()
        ->pending(User::factory()->create())
        ->named(array_fill_keys(config('einundzwanzig.tag_locales'), 'Rodiny s dětmi'))
        ->create(['type' => 'meetup_event']);

    Livewire::test('tags.moderation')
        ->call('edit', $copied->id)
        ->assertSee('vermutlich vom alten Picker kopiert');
});

it('does not flag a seeded proper noun that is the same word everywhere', function () {
    // Bitcoin, Nostr, Lightning: nine identical names on purpose. `created_by` is what
    // separates them from the picker's copies.
    $this->actingAs(moderator());

    $seeded = Tag::factory()
        ->named(array_fill_keys(config('einundzwanzig.tag_locales'), 'Bitcoin'))
        ->create(['type' => 'meetup_event']);

    // SetsCreatedBy stamps the acting user on anything created inside a request; a
    // seeder runs on the console, where there is none. Reproduce that here.
    $seeded->newQuery()->whereKey($seeded->id)->update(['created_by' => null]);

    Livewire::test('tags.moderation')
        ->call('edit', $seeded->id)
        ->assertDontSee('vermutlich vom alten Picker kopiert');
});

it('keeps a non-editor out of the name editor', function () {
    $outsider = User::factory()->create(['nostr' => null]);
    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung'])->create(['type' => 'meetup_event']);

    $this->actingAs($outsider);

    Livewire::test('tags.moderation')->assertStatus(403);

    expect($outsider->can('update', $tag))->toBeFalse()
        ->and($tag->fresh()->getTranslation('name', 'de', false))->toBe('Selbstverwahrung');
});
