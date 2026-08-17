<?php

use App\Models\City;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
 * Regression net for fd48fa7 (SetApiLocale middleware, app/Http/Middleware/
 * SetApiLocale.php): the API must answer English (default Laravel validation
 * messages AND messages() overrides) without touching config('app.locale'),
 * which the web UI and the slug generation of Meetup/City/Lecturer
 * still rely on being 'de'. See SlugLocaleStabilityApiTest for the slug side
 * of this same bug.
 */

it('returns an english message for a plain laravel validation rule', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/meetup', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('returns an english message for a messages() override', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/meetup', [
        'name' => 'Bitcoin Meetup Test',
        'city_id' => 999999,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.city_id.0', 'The specified city does not exist.');
});

it('leaves the app locale untouched by an api request, so the web ui stays german', function () {
    expect(config('app.locale'))->toBe('de');

    Sanctum::actingAs($user = User::factory()->create());
    $city = City::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/cities/'.$city->id, [
        'population' => 54321,
    ])->assertSuccessful();

    expect(config('app.locale'))->toBe('de');
});
