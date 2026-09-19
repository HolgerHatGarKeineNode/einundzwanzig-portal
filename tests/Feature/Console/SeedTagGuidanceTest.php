<?php

use App\Models\Tag;
use Database\Seeders\TagSeeder;

it('fills empty descriptions when run against an existing set', function () {
    // A production-like start: the vocabulary exists, the descriptions do not.
    $this->seed(TagSeeder::class);
    Tag::query()->update(['description' => null]);

    $bitcoin = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Bitcoin');

    expect($bitcoin->getTranslation('description', 'de', false))->toBeNull();

    $this->artisan('tags:seed-guidance')
        ->expectsOutputToContain('Tags with a German description: 0 before, 16 after.')
        ->assertSuccessful();

    expect($bitcoin->fresh()->getTranslation('description', 'de', false))->not->toBe('');
});

it('is idempotent and leaves edited texts alone', function () {
    $this->seed(TagSeeder::class);

    $beginners = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Einsteiger');

    $beginners->setTranslation('description', 'de', 'Bleibt so.');
    $beginners->save();

    $this->artisan('tags:seed-guidance')->assertSuccessful();

    expect($beginners->fresh()->getTranslation('description', 'de', false))->toBe('Bleibt so.');
});
