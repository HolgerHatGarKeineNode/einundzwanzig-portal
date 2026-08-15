<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('updates an existing Meetup name as the creator', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Updated Name')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->refresh()->name)->toBe('Updated Name');
});

it('rejects update when name collides with another existing Meetup', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);
    Meetup::factory()->create(['name' => 'Other Name', 'city_id' => $this->city->id]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Other Name')
        ->call('updateMeetup')
        ->assertHasErrors(['name' => 'unique']);
});

it('allows update when name is unchanged (Rule::unique ignores own id)', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Original Name')
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();
});

it('allows updateMeetup for a delegated leader who is not the creator', function () {
    $leader = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => User::factory()->create()->id,
    ]);
    $meetup->users()->attach($leader, ['is_leader' => true]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Updated By Leader')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->refresh()->name)->toBe('Updated By Leader');
});

it('blocks updateMeetup for a plain member (is_leader = false) who is not the creator', function () {
    $member = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => User::factory()->create()->id,
    ]);
    $meetup->users()->attach($member, ['is_leader' => false]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertStatus(403);

    expect($meetup->refresh()->name)->toBe('Original Name');
});

it('blocks updateMeetup when the user is neither creator nor pivot member', function () {
    actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => User::factory()->create()->id,
    ]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertStatus(403);

    expect($meetup->refresh()->name)->toBe('Original Name');
});

it('does not crash with FileNotPreviewableException when a psd is uploaded as new logo', function () {
    Storage::fake(FileUploadConfiguration::disk());

    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('logo', UploadedFile::fake()->create('logo.psd', 100, 'image/vnd.adobe.photoshop'))
        ->set('name', 'Updated Name')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasErrors(['logo']);

    expect($meetup->refresh()->name)->toBe('Original Name')
        ->and($meetup->getFirstMedia('logo'))->toBeNull();
});

it('blocks updateMeetup after exceeding the hourly rate limit', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Original Name',
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit(Meetup::updateRateLimitKey($creator->id), 3600);
    }

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Updated Name')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasErrors(['rateLimit']);

    expect($meetup->refresh()->name)->toBe('Original Name');
});

it('redirects guests when accessing meetup-edit', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);

    $this->get('/de/meetup-edit/'.$meetup->id)->assertRedirect(route('login'));
});
