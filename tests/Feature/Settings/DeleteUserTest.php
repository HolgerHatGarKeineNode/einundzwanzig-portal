<?php

use App\Models\City;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('deletes an account with a placeholder password by the confirmation word alone', function () {
    $user = actingAsUser(['password' => Hash::make('placeholder')]);

    Livewire::test('settings.delete-user-form')
        ->assertDontSeeHtml('type="password"')
        ->set('confirmation', 'DELETE')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(User::query()->find($user->id))->toBeNull();
    expect(auth()->check())->toBeFalse();
});

it('deletes an account once the confirmation word is typed', function (string $locale, string $typed) {
    App::setLocale($locale);
    $user = actingAsUser(['password' => null]);

    Livewire::test('settings.delete-user-form')
        ->assertDontSeeHtml('type="password"')
        ->set('confirmation', $typed)
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(User::query()->find($user->id))->toBeNull();
    expect(auth()->check())->toBeFalse();
})->with([
    'english word' => ['en', 'DELETE'],
    'lower case and padded' => ['en', '  delete '],
    'translated word' => ['de', 'löschen'],
    'english word in another locale' => ['de', 'DELETE'],
]);

it('does not delete an account without the confirmation word', function (string $typed) {
    $user = actingAsUser(['password' => null]);

    Livewire::test('settings.delete-user-form')
        ->set('confirmation', $typed)
        ->call('deleteUser')
        ->assertHasErrors('confirmation');

    expect(User::query()->find($user->id))->not->toBeNull();
    expect(auth()->check())->toBeTrue();
})->with(['empty' => '', 'wrong word' => 'yes']);

it('keeps the content a deleted user created, without a creator', function () {
    $user = actingAsUser(['password' => null]);
    $city = City::factory()->create(['created_by' => $user->id]);
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'city_id' => $city->id]);
    $event = MeetupEvent::factory()->create(['created_by' => $user->id, 'meetup_id' => $meetup->id]);

    Livewire::test('settings.delete-user-form')
        ->set('confirmation', 'DELETE')
        ->call('deleteUser')
        ->assertHasNoErrors();

    foreach ([$city, $meetup, $event] as $record) {
        expect($record->fresh())->not->toBeNull()
            ->and($record->fresh()->created_by)->toBeNull();
    }
});

it('removes the sign-in credentials of a deleted account and keeps everyone else\'s', function () {
    $badgeId = DB::table('badges')->insertGetId(['name' => 'Pleb', 'level' => 1]);
    $seedCredentials = function (User $user, string $suffix) use ($badgeId): void {
        $user->createToken('device-'.$suffix);
        DB::table('oauth_access_tokens')->insert(['id' => 'at-'.$suffix, 'user_id' => $user->id, 'client_id' => 'client', 'revoked' => false]);
        DB::table('oauth_refresh_tokens')->insert(['id' => 'rt-'.$suffix, 'access_token_id' => 'at-'.$suffix, 'revoked' => false]);
        DB::table('oauth_auth_codes')->insert(['id' => 'ac-'.$suffix, 'user_id' => $user->id, 'client_id' => 'client', 'revoked' => false]);
        DB::table('oauth_device_codes')->insert(['id' => 'dc-'.$suffix, 'user_id' => $user->id, 'client_id' => 'client', 'user_code' => 'UC-'.$suffix, 'scopes' => '[]', 'revoked' => false]);
        DB::table('sessions')->insert(['id' => 'sess-'.$suffix, 'user_id' => $user->id, 'payload' => '', 'last_activity' => 0]);
        DB::table('login_keys')->insert(['k1' => str_repeat($suffix === 'gone' ? 'a' : 'b', 64), 'user_id' => $user->id]);
        DB::table('user_badges')->insert(['user_id' => $user->id, 'badge_id' => $badgeId]);
    };
    $countFor = fn (User $user, string $suffix): array => [
        'personal_access_tokens' => $user->tokens()->count(),
        'oauth_access_tokens' => DB::table('oauth_access_tokens')->where('user_id', $user->id)->count(),
        'oauth_refresh_tokens' => DB::table('oauth_refresh_tokens')->where('id', 'rt-'.$suffix)->count(),
        'oauth_auth_codes' => DB::table('oauth_auth_codes')->where('user_id', $user->id)->count(),
        'oauth_device_codes' => DB::table('oauth_device_codes')->where('user_id', $user->id)->count(),
        'sessions' => DB::table('sessions')->where('user_id', $user->id)->count(),
        'login_keys' => DB::table('login_keys')->where('user_id', $user->id)->count(),
        'user_badges' => DB::table('user_badges')->where('user_id', $user->id)->count(),
    ];

    $other = User::factory()->create();
    $seedCredentials($other, 'kept');
    $user = actingAsUser();
    $seedCredentials($user, 'gone');

    Livewire::test('settings.delete-user-form')
        ->set('confirmation', 'DELETE')
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect($countFor($user, 'gone'))->each->toBe(0)
        ->and($countFor($other, 'kept'))->each->toBe(1);
});
