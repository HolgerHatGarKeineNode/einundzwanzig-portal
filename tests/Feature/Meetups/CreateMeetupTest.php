<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Services\Osm\NominatimClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('creates a Meetup when authenticated user submits a valid form', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'Berlin Bitcoin Meetup')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasNoErrors()
        ->assertRedirect();

    $meetup = Meetup::query()->where('name', 'Berlin Bitcoin Meetup')->first();
    expect($meetup)->not->toBeNull()
        ->and($meetup->city_id)->toBe($this->city->id);
});

it('accepts an avif file into the logo media collection', function () {
    Storage::fake('public');

    $path = sys_get_temp_dir().'/'.uniqid('avif_', true).'.avif';
    imageavif(imagecreatetruecolor(1, 1), $path);

    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);

    $meetup->addMedia($path)->toMediaCollection('logo');

    expect($meetup->getFirstMedia('logo'))->not->toBeNull();
});

it('rejects creation without a name', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasErrors(['name' => 'required']);
});

it('rejects creation without city_id', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'No City Meetup')
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasErrors(['city_id' => 'required']);
});

it('rejects creation with non-existent city_id', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'Bad City Meetup')
        ->set('city_id', 999999)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasErrors(['city_id' => 'exists']);
});

it('rejects creation with a duplicate meetup name', function () {
    Meetup::factory()->create(['name' => 'Already Exists', 'city_id' => $this->city->id]);
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'Already Exists')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        // Objekt-Regel UniqueMeetupName statt 'unique': sie prueft zusaetzlich
        // ohne Ruecksicht auf Gross-/Kleinschreibung und traegt keinen Regelnamen.
        ->assertHasErrors(['name']);
});

it('rejects creation when telegram_link is not a valid URL', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'Bad URL Meetup')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->set('telegram_link', 'not-a-url')
        ->call('createMeetup')
        ->assertHasErrors(['telegram_link' => 'url']);
});

it('does not crash with FileNotPreviewableException when a psd is uploaded as logo', function () {
    Storage::fake(FileUploadConfiguration::disk());
    actingAsUser();

    // Regression: temporaryUrl() im Blade crashte bei nicht-vorschaubaren
    // Mimes (psd) mit einem 500, obwohl die Validierung den Fehler bereits
    // in der Error-Bag hatte. Siehe FileNotPreviewableException vom 2026-08-03.
    Livewire::test('meetups.create')
        ->set('logo', UploadedFile::fake()->create('logo.psd', 100, 'image/vnd.adobe.photoshop'))
        ->assertHasErrors(['logo']);
});

it('does not create a Meetup with a psd logo on submit', function () {
    Storage::fake(FileUploadConfiguration::disk());
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('logo', UploadedFile::fake()->create('logo.psd', 100, 'image/vnd.adobe.photoshop'))
        ->set('name', 'PSD Logo Meetup')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasErrors(['logo']);

    expect(Meetup::query()->where('name', 'PSD Logo Meetup')->exists())->toBeFalse();
});

it('redirects guests to login when accessing meetup-create', function () {
    $this->get('/de/meetup-create')->assertRedirect(route('login'));
});

it('creates a city from a Nominatim hit within the meetup-create flow', function () {
    NominatimClient::resetThrottle();
    Cache::flush();
    app()->bind(NominatimClient::class, fn (): NominatimClient => new NominatimClient(minIntervalMs: 0));
    actingAsUser();

    Http::fake(['*' => Http::response([[
        'osm_type' => 'relation',
        'osm_id' => 62422,
        'name' => 'Hamburg',
        'display_name' => 'Hamburg, Deutschland',
        'lat' => '53.5511',
        'lon' => '9.9937',
        'category' => 'place',
    ]])]);

    $form = Livewire::test('meetups.create')
        ->set('newCityCountryId', $this->city->country_id)
        ->set('newCityQuery', 'Hamburg')
        ->call('searchCity')
        ->call('chooseCity', 0)
        ->assertHasNoErrors();

    $city = City::query()->where('name', 'Hamburg')->first();

    expect($city)->not->toBeNull()
        ->and($form->get('city_id'))->toBe($city->id)
        ->and((float) $city->latitude)->toBe(53.5511)
        ->and((float) $city->longitude)->toBe(9.9937)
        ->and($city->osm_id)->toBe(62422);
});
