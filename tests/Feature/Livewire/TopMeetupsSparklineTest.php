<?php

use App\Models\Meetup;
use App\Models\User;
use Livewire\Livewire;

/*
 * A <flux:chart> with a single data point makes Flux's ui-chart console.warn in
 * the visitor's browser ("chart only has one data point so charts with lines or
 * areas cannot be rendered"), and a server-side test cannot see a console. The
 * sibling top-countries view guards the chart with count(sparkline) > 1; this
 * pins the same guard on top-meetups, where a meetup without attendees gets
 * sparkline = [0]. Found by the browser latch on /de/meetups.
 */

it('renders no chart for a meetup that has no attendees', function () {
    $meetup = Meetup::factory()->create(['name' => 'Leere Runde']);

    Livewire::withoutLazyLoading()->test('dashboard.top-meetups')
        ->assertSee('Leere Runde')
        ->assertDontSeeHtml('<ui-chart');
});

it('renders no chart when every attendee joined in the same month', function () {
    $meetup = Meetup::factory()->create(['name' => 'Frische Runde']);
    $meetup->users()->attach(User::factory()->create(['created_at' => now()]));

    Livewire::withoutLazyLoading()->test('dashboard.top-meetups')
        ->assertSee('Frische Runde')
        ->assertDontSeeHtml('<ui-chart');
});

it('still renders the chart once the attendees span more than one month', function () {
    $meetup = Meetup::factory()->create(['name' => 'Alte Runde']);
    $meetup->users()->attach(User::factory()->create(['created_at' => now()->subMonths(5)]));

    Livewire::withoutLazyLoading()->test('dashboard.top-meetups')
        ->assertSee('Alte Runde')
        ->assertSeeHtml('<ui-chart');
});
