<?php

use App\Models\City;
use App\Models\Country;

it('lets an authenticated user open the meetup-create page', function () {
    actingAsUser();
    $country = Country::factory()->create(['code' => 'de']);
    City::factory()->create(['country_id' => $country->id]);

    $page = visit('/de/meetup-create');

    $page->assertSee('Meetup')
        ->assertNoJavaScriptErrors();
});

it('lets an authenticated user open the service-create page', function () {
    actingAsUser();

    $page = visit('/de/service-create');

    $page->assertSee('Service')
        ->assertNoJavaScriptErrors();
});

it('lets an authenticated user open the lecturer-create page', function () {
    actingAsUser();

    $page = visit('/de/lecturer-create');

    $page->assertSee(__('Dozent'))
        ->assertNoJavaScriptErrors();
});

it('opens settings/profile for an authenticated user', function () {
    actingAsUser(['name' => 'Browser Tester']);

    $page = visit('/de/settings/profile');

    $page->assertSee('Browser Tester')
        ->assertNoJavaScriptErrors();
});
