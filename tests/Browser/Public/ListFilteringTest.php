<?php

use App\Enums\SelfHostedServiceType;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\SelfHostedService;

/**
 * NOTE: The Search-Inputs of the index pages use Postgres `ilike`, which the
 * SQLite test database does not support. We therefore exercise the type-badge
 * filter (uses plain `=`) instead and assert the index list itself reacts
 * to it.
 */
beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);
});

it('renders all seeded services on the public services index', function () {
    SelfHostedService::factory()->create(['name' => 'NodeAlpha', 'type' => SelfHostedServiceType::Mempool]);
    SelfHostedService::factory()->create(['name' => 'BetaService', 'type' => SelfHostedServiceType::Other]);

    $page = visit('/de/services');

    $page->assertSee('NodeAlpha')
        ->assertSee('BetaService')
        ->assertNoJavaScriptErrors();
});

it('filters services by clicking a type-badge in the type-cloud', function () {
    SelfHostedService::factory()->create(['name' => 'OnlyMempoolNode', 'type' => SelfHostedServiceType::Mempool]);
    SelfHostedService::factory()->create(['name' => 'OnlyOtherThing', 'type' => SelfHostedServiceType::Other]);

    $page = visit('/de/services');

    $page->assertSee('OnlyMempoolNode')
        ->assertSee('OnlyOtherThing')
        ->click('[wire\\:click="filterByType(\'mempool\')"]')
        ->wait(1)
        ->assertSee('OnlyMempoolNode')
        ->assertDontSee('OnlyOtherThing')
        ->assertNoJavaScriptErrors();
});

it('shows seeded meetups on the public meetups index', function () {
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'BrowserSeeded Meetup XYZ',
        'visible_on_map' => true,
    ]);

    $page = visit('/de/meetups');

    $page->assertSee('BrowserSeeded Meetup XYZ')
        ->assertNoJavaScriptErrors();
});
