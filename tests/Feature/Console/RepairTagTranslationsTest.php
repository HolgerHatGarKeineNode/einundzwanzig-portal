<?php

use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Writes a tag exactly the way the broken TagFactory used to: a json_encode()'d
 * translation map handed to a translatable attribute, which Spatie then stored whole
 * inside the current locale.
 */
function insertBrokenTag(string $name = 'sint-2756'): int
{
    return DB::table('tags')->insertGetId([
        'name' => json_encode(['de' => json_encode(['en' => $name, 'de' => $name])]),
        'slug' => json_encode(['de' => 'en'.$name.'de'.$name]),
        'type' => 'topic',
        'featured' => false,
        'approved_at' => now(),
        'icon' => 'tag',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('unwraps a doubly encoded translation map', function () {
    $id = insertBrokenTag();

    $this->artisan('tags:repair-translations')->assertSuccessful();

    $raw = DB::table('tags')->where('id', $id)->first();

    expect(json_decode($raw->name, true))->toBe(['en' => 'sint-2756', 'de' => 'sint-2756']);
});

it('regenerates the slugs that were derived from the broken name', function () {
    $id = insertBrokenTag();

    expect(json_decode(DB::table('tags')->where('id', $id)->value('slug'), true))
        ->toBe(['de' => 'ensint-2756desint-2756']);

    $this->artisan('tags:repair-translations')->assertSuccessful();

    expect(json_decode(DB::table('tags')->where('id', $id)->value('slug'), true))
        ->toBe(['en' => 'sint-2756', 'de' => 'sint-2756']);
});

it('leaves healthy tags untouched', function () {
    $healthy = Tag::factory()->named(['en' => 'Bitcoin Basics', 'de' => 'Bitcoin Grundlagen'])->create();
    $before = DB::table('tags')->where('id', $healthy->id)->first();

    $this->artisan('tags:repair-translations')
        ->expectsOutputToContain('0 tag(s)')
        ->assertSuccessful();

    $after = DB::table('tags')->where('id', $healthy->id)->first();

    expect($after->name)->toBe($before->name)
        ->and($after->slug)->toBe($before->slug);
});

it('is idempotent', function () {
    insertBrokenTag();

    $this->artisan('tags:repair-translations')->assertSuccessful();

    $this->artisan('tags:repair-translations')
        ->expectsOutputToContain('Repaired 0 tag(s)')
        ->assertSuccessful();
});

it('changes nothing on a dry run', function () {
    $id = insertBrokenTag();
    $before = DB::table('tags')->where('id', $id)->first();

    $this->artisan('tags:repair-translations', ['--dry-run' => true])
        ->expectsOutputToContain('Would repair 1 tag(s)')
        ->assertSuccessful();

    expect(DB::table('tags')->where('id', $id)->first())->toEqual($before);
});

it('does not touch slugs when only the description was broken', function () {
    // A healthy name and slug, but a description written the broken way. Regenerating
    // slugs here would rewrite a correct, stable URL for no reason.
    $id = DB::table('tags')->insertGetId([
        'name' => json_encode(['de' => 'Bitcoin Grundlagen']),
        'slug' => json_encode(['de' => 'ein-bewusst-abweichender-slug']),
        'description' => json_encode(['de' => json_encode(['en' => 'Basics', 'de' => 'Grundlagen'])]),
        'type' => 'topic',
        'featured' => false,
        'approved_at' => now(),
        'icon' => 'tag',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('tags:repair-translations')->assertSuccessful();

    $row = DB::table('tags')->where('id', $id)->first();

    expect(json_decode($row->slug, true))->toBe(['de' => 'ein-bewusst-abweichender-slug'])
        ->and(json_decode($row->description, true))->toBe(['en' => 'Basics', 'de' => 'Grundlagen']);
});

it('produces a tag the model can read back', function () {
    insertBrokenTag('bitcoin');

    $this->artisan('tags:repair-translations')->assertSuccessful();

    $tag = Tag::query()->latest('id')->first();

    expect($tag->getTranslation('name', 'en'))->toBe('bitcoin')
        ->and($tag->getTranslation('slug', 'de'))->toBe('bitcoin');
});
