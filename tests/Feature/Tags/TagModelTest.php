<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;

it('stores translations as a flat locale map, not a nested json string', function () {
    $tag = Tag::factory()->named(['en' => 'Bitcoin Basics', 'de' => 'Bitcoin Grundlagen'])->create();

    $raw = $tag->getAttributes()['name'];

    expect(json_decode($raw, true))
        ->toBe(['en' => 'Bitcoin Basics', 'de' => 'Bitcoin Grundlagen']);

    // The regression this guards: a JSON string stored inside a single locale.
    expect($raw)->not->toContain('\\"');
});

it('generates one slug per translated locale', function () {
    $tag = Tag::factory()->named(['en' => 'Bitcoin Basics', 'cs' => 'Základy Bitcoinu'])->create();

    expect($tag->getTranslation('slug', 'en'))->toBe('bitcoin-basics')
        ->and($tag->getTranslation('slug', 'cs'))->toBe('zaklady-bitcoinu');
});

it('keeps an existing slug when the name changes', function () {
    $tag = Tag::factory()->named(['de' => 'Bitcoin Grundlagen'])->create();

    expect($tag->getTranslation('slug', 'de'))->toBe('bitcoin-grundlagen');

    $tag->setTranslation('name', 'de', 'Bitcoin Grundlagen Neu');
    $tag->save();

    expect($tag->fresh()->getTranslation('slug', 'de'))->toBe('bitcoin-grundlagen');
});

it('keeps slugs stable when the app locale changes', function () {
    // Regression net for the class of bug behind commit fd48fa7, where saving a record
    // under a different locale silently rewrote its public slug.
    $tag = Tag::factory()->named(['de' => 'Nürnberg Treff', 'en' => 'Nuremberg Meetup'])->create();

    $before = $tag->getAttributes()['slug'];

    app()->setLocale('en');
    $tag->touch();
    $tag->save();

    expect($tag->fresh()->getAttributes()['slug'])->toBe($before);
});

it('adds a slug for a locale added later without touching the others', function () {
    $tag = Tag::factory()->named(['de' => 'Bitcoin Grundlagen'])->create();

    $tag->setTranslation('name', 'cs', 'Základy Bitcoinu');
    $tag->save();

    $fresh = $tag->fresh();

    expect($fresh->getTranslation('slug', 'de'))->toBe('bitcoin-grundlagen')
        ->and($fresh->getTranslation('slug', 'cs'))->toBe('zaklady-bitcoinu');
});

it('casts featured and approved_at', function () {
    $tag = Tag::factory()->featured()->create();

    // DateTimeInterface rather than a concrete class: this project swaps in its own
    // App\Support\Carbon, so asserting on Illuminate's would test the wrong thing.
    expect($tag->featured)->toBeTrue()
        ->and($tag->approved_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($tag->isApproved())->toBeTrue();
});

it('separates approved, pending and featured tags by scope', function () {
    $approved = Tag::factory()->create();
    $featured = Tag::factory()->featured()->create();
    $pending = Tag::factory()->pending()->create();

    expect(Tag::query()->approved()->pluck('id'))
        ->toContain($approved->id, $featured->id)
        ->not->toContain($pending->id);

    expect(Tag::query()->pending()->pluck('id'))->toContain($pending->id)
        ->not->toContain($approved->id);

    expect(Tag::query()->featured()->pluck('id'))->toContain($featured->id)
        ->not->toContain($approved->id);
});

it('offers a pending tag to its author but not to anyone else', function () {
    $author = User::factory()->create();
    $stranger = User::factory()->create();

    $approved = Tag::factory()->create();
    $ownSuggestion = Tag::factory()->pending($author)->create();
    $foreignSuggestion = Tag::factory()->pending($stranger)->create();

    expect(Tag::query()->selectableBy($author)->pluck('id'))
        ->toContain($approved->id, $ownSuggestion->id)
        ->not->toContain($foreignSuggestion->id);

    expect(Tag::query()->selectableBy(null)->pluck('id'))
        ->toContain($approved->id)
        ->not->toContain($ownSuggestion->id, $foreignSuggestion->id);
});

it('records who suggested a tag', function () {
    $user = User::factory()->create();

    $tag = Tag::factory()->pending($user)->create();

    expect($tag->creator->id)->toBe($user->id)
        ->and($tag->isApproved())->toBeFalse();
});

it('never shows an empty name for a tag that has one in some language', function () {
    // The measured failure this guards: Spatie falls back to app.fallback_locale only
    // if that language is translated, and fallbackAny is off — so a German-only tag
    // asked for in Czech returned "". 84 of the 89 production tags are German-only.
    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung'])->create();

    expect($tag->getTranslation('name', 'cs'))->toBe('')          // the raw behaviour
        ->and($tag->displayName('cs'))->toBe('Selbstverwahrung')  // what we show instead
        ->and($tag->displayLocale('cs'))->toBe('de')
        ->and($tag->isDisplayNameSubstituted('cs'))->toBeTrue();
});

it('prefers the requested language over any fallback', function () {
    $tag = Tag::factory()->named([
        'de' => 'Selbstverwahrung',
        'en' => 'Self-custody',
        'cs' => 'Samosprava',
    ])->create();

    expect($tag->displayName('cs'))->toBe('Samosprava')
        ->and($tag->displayLocale('cs'))->toBe('cs')
        ->and($tag->isDisplayNameSubstituted('cs'))->toBeFalse();
});

it('falls back to english before any other language', function () {
    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung', 'en' => 'Self-custody'])->create();

    expect($tag->displayName('cs'))->toBe('Self-custody')
        ->and($tag->displayLocale('cs'))->toBe('en');
});

it('uses the current app locale when none is given', function () {
    $tag = Tag::factory()->named(['de' => 'Selbstverwahrung'])->create();

    app()->setLocale('de');
    expect($tag->displayName())->toBe('Selbstverwahrung')
        ->and($tag->isDisplayNameSubstituted())->toBeFalse();

    app()->setLocale('pl');
    expect($tag->displayName())->toBe('Selbstverwahrung')
        ->and($tag->isDisplayNameSubstituted())->toBeTrue();
});

it('reports no display locale for a tag without any name', function () {
    // make(), not create(): a nameless tag cannot be persisted at all, because no name
    // means no slug and tags.slug is NOT NULL. The guard is for defensive callers, so
    // it is exercised on an unsaved instance.
    $tag = Tag::factory()->named([])->make();

    expect($tag->displayLocale('cs'))->toBeNull()
        ->and($tag->displayName('cs'))->toBe('');
});

it('refuses to persist a tag without any name', function () {
    expect(fn () => Tag::factory()->named([])->create())
        ->toThrow(QueryException::class);
});

it('approves a pending tag', function () {
    $tag = Tag::factory()->pending()->create();

    expect($tag->isApproved())->toBeFalse();

    $tag->approve();

    expect($tag->fresh()->isApproved())->toBeTrue();
});
