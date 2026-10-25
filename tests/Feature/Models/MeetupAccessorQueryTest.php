<?php

use App\Models\City;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\DB;

/*
 * Eloquent calls an accessor METHOD on every access, cached or not
 * (mergeAttributeFromAttributeCasts() looks for a setter). A query in the method
 * body therefore re-ran per access: meetups/index read `$meetup->nextEvent[...]`
 * up to eight times per card, 81 queries for ten meetups (measured 2026-10-25).
 */
function queriesWhile(callable $callback, string $needle): int
{
    $count = 0;
    DB::listen(function ($query) use (&$count, $needle) {
        if (str_contains($query->sql, $needle)) {
            $count++;
        }
    });

    $callback();

    return $count;
}

beforeEach(function () {
    $city = City::factory()->create(['country_id' => portalCountry()->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id]);
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addDay()]);

    $this->meetup = Meetup::query()->with('city.country')->findOrFail($meetup->id);
});

it('queries the next event once, however often it is read', function () {
    $count = queriesWhile(function () {
        foreach (range(1, 5) as $ignored) {
            expect($this->meetup->nextEvent['id'])->not->toBeNull();
        }
    }, 'from "meetup_events"');

    expect($count)->toBe(1);
});
