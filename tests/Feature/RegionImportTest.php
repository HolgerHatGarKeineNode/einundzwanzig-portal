<?php

use App\Models\Country;
use App\Models\Region;

it('imports the US states and is idempotent', function () {
    $country = Country::factory()->create(['code' => 'us']);

    $this->artisan('regions:import', ['--country' => ['us']])->assertSuccessful();

    expect(Region::where('country_id', $country->id)->count())->toBe(51);

    $indiana = Region::findByCountryCodeAndCode('us', 'in');
    expect($indiana)->not->toBeNull()
        ->and($indiana->name)->toBe('Indiana')
        ->and($indiana->slug)->toBe('indiana')
        ->and($indiana->isoCode())->toBe('US-IN');

    // Zweiter Lauf darf nichts verdoppeln — der Import laeuft nach jedem Deploy erneut.
    $this->artisan('regions:import', ['--country' => ['us']])->assertSuccessful();

    expect(Region::where('country_id', $country->id)->count())->toBe(51);
});

it('imports several countries at once', function () {
    Country::factory()->create(['code' => 'us']);
    Country::factory()->create(['code' => 'de']);

    $this->artisan('regions:import', ['--country' => ['us', 'de']])->assertSuccessful();

    expect(Region::count())->toBe(67)
        ->and(Region::findByCountryCodeAndCode('de', 'by')?->name)->toBe('Bayern');
});

it('matches the country code case-insensitively', function () {
    // Produktion fuehrt "us", die lokalen Stammdaten "US" — beides muss greifen.
    Country::factory()->create(['code' => 'US']);

    $this->artisan('regions:import', ['--country' => ['us']])->assertSuccessful();

    expect(Region::count())->toBe(51);
});

it('warns about an unknown country instead of failing silently', function () {
    Country::factory()->create(['code' => 'de']);

    $this->artisan('regions:import', ['--country' => ['xx']])
        ->expectsOutputToContain("Land 'xx' existiert nicht")
        ->assertFailed();

    expect(Region::count())->toBe(0);
});
