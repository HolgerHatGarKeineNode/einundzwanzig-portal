<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('snapshots the current master data of all meetups', function () {
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Einundzwanzig Cham',
    ]);

    $this->artisan('meetups:snapshot')
        ->expectsOutputToContain('Restore points saved for 1 meetups.')
        ->assertSuccessful();

    $restorePoint = $meetup->refresh()->restore_point;
    expect($restorePoint['attributes']['name'])->toBe('Einundzwanzig Cham')
        ->and($restorePoint['captured_at'])->not->toBeNull();
});

it('snapshots a single meetup by id', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $other = Meetup::factory()->create(['city_id' => $this->city->id]);

    $this->artisan('meetups:snapshot', ['meetup' => $meetup->id])
        ->assertSuccessful();

    expect($meetup->refresh()->restore_point)->not->toBeNull()
        ->and($other->refresh()->restore_point)->toBeNull();
});

it('restores master data from the restore point', function () {
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Einundzwanzig Cham',
        'community' => 'einundzwanzig',
        'telegram_link' => 'https://t.me/original',
    ]);

    $this->artisan('meetups:snapshot', ['meetup' => $meetup->id])->assertSuccessful();

    $meetup->update([
        'name' => 'Vandalized Name',
        'community' => 'other',
        'telegram_link' => 'https://t.me/spam',
    ]);

    $this->artisan('meetups:restore', ['meetup' => $meetup->id])
        ->expectsOutputToContain('restored from restore point')
        ->assertSuccessful();

    $meetup->refresh();
    expect($meetup->name)->toBe('Einundzwanzig Cham')
        ->and($meetup->community)->toBe('einundzwanzig')
        ->and($meetup->telegram_link)->toBe('https://t.me/original');
});

it('fails to restore when no restore point exists', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);

    $this->artisan('meetups:restore', ['meetup' => $meetup->id])
        ->expectsOutputToContain('has no restore point')
        ->assertFailed();
});

it('fails to restore an unknown meetup', function () {
    $this->artisan('meetups:restore', ['meetup' => 999999])
        ->expectsOutputToContain('not found')
        ->assertFailed();
});

it('does not allow mass-assigning the restore point via update', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);

    $meetup->update(['restore_point' => ['attributes' => ['name' => 'Hacked']]]);

    expect($meetup->refresh()->restore_point)->toBeNull();
});
