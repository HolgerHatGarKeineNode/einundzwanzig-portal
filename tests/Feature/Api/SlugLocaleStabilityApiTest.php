<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
 * Regression net for fd48fa7: SetApiLocale used to call App::setLocale('en'),
 * which also writes config('app.locale') — and getSlugOptions() on Meetup,
 * City and Lecturer read exactly that value as its slug-language
 * fallback (usingLanguage(Cookie::get('lang', config('app.locale')))). Since
 * generateSlugsOnUpdate is on by default, any PATCH through the API — even
 * on a field that has nothing to do with the slug — regenerated it in
 * English, silently rewriting the public URL (e.g. "nuernberg" -> "nurnberg").
 *
 * Each case creates the record WITHOUT a preset slug: the factories normally
 * write 'slug' => Str::slug($name) themselves, and Spatie\Sluggable's
 * GenerateSlugAction::hasCustomSlugBeenUsed() treats any non-blank slug
 * present before the "creating" event as custom and keeps it verbatim —
 * bypassing getSlugOptions() (and its locale) entirely. Passing 'slug' => ''
 * forces the real, language-aware generation path to run at creation time,
 * so the baseline slug reflects the app's German locale exactly like a
 * production record would.
 */

it('keeps a meetups german slug stable after an api patch to an unrelated field', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $meetup = Meetup::factory()->create([
        'name' => 'Bitcoin Meetup Nürnberg '.random_int(100000, 999999),
        'slug' => '',
        'created_by' => $user->id,
    ]);

    $slugBefore = $meetup->slug;
    expect($slugBefore)->toContain('nuernberg')->not->toContain('nurnberg');

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'intro' => 'Ein komplett anderer Text, der nichts mit dem Namen zu tun hat.',
    ])->assertSuccessful();

    expect($meetup->fresh()->slug)->toBe($slugBefore);
});

it('keeps a citys german slug stable after an api patch to an unrelated field', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $country = Country::factory()->create(['code' => 'DE']);
    $city = City::factory()->create([
        'name' => 'Nürnberg '.random_int(100000, 999999),
        'country_id' => $country->id,
        'slug' => '',
        'created_by' => $user->id,
    ]);

    $slugBefore = $city->slug;
    expect($slugBefore)->toContain('nuernberg')->not->toContain('nurnberg');

    $this->patchJson('/api/cities/'.$city->id, [
        'population' => 12345,
    ])->assertSuccessful();

    expect($city->fresh()->slug)->toBe($slugBefore);
});

it('keeps a lecturers german slug stable after an api patch to an unrelated field', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $lecturer = Lecturer::factory()->create([
        'name' => 'Nürnberg Lecturer '.random_int(100000, 999999),
        'slug' => '',
        'created_by' => $user->id,
    ]);

    $slugBefore = $lecturer->slug;
    expect($slugBefore)->toContain('nuernberg')->not->toContain('nurnberg');

    $this->patchJson('/api/lecturers/'.$lecturer->id, [
        'subtitle' => 'Ein ganz anderer Untertitel',
    ])->assertSuccessful();

    expect($lecturer->fresh()->slug)->toBe($slugBefore);
});
