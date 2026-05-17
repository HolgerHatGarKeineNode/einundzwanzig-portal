<?php

use App\Models\City;
use App\Models\Country;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    City::factory()->create(['country_id' => $country->id]);
});

it('renders the sidebar with the user profile reachable on mobile viewport', function () {
    actingAsUser(['name' => 'Sidebar Tester']);

    $page = visit('/de/dashboard');

    $page->resize(390, 844)
        ->click('[aria-label="Menü öffnen"]')
        ->assertSee('Dashboard')
        ->assertSee('Repository')
        ->assertSee('Sidebar Tester');
});

it('renders the sidebar with the user profile on desktop viewport', function () {
    actingAsUser(['name' => 'Sidebar Tester']);

    $page = visit('/de/dashboard');

    $page->resize(1280, 800)
        ->assertSee('Dashboard')
        ->assertSee('Repository')
        ->assertSee('Sidebar Tester');
});
