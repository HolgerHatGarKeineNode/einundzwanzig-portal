<?php

use App\Models\City;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('deletes the user and logs them out when password is correct', function () {
    $user = actingAsUser(['password' => Hash::make('correct-password')]);

    Livewire::test('settings.delete-user-form')
        ->set('password', 'correct-password')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(User::query()->find($user->id))->toBeNull();
    expect(auth()->check())->toBeFalse();
});

it('does not delete the user with an incorrect password', function () {
    $user = actingAsUser(['password' => Hash::make('correct-password')]);

    Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser')
        ->assertHasErrors(['password' => 'current_password']);

    expect(User::query()->find($user->id))->not->toBeNull();
});

it('deletes a passwordless account once the confirmation word is typed', function (string $locale, string $typed) {
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

it('does not delete a passwordless account without the confirmation word', function (string $typed) {
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
