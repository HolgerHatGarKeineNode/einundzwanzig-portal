<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Support\TagUsageStats;

/*
|--------------------------------------------------------------------------
| The usage heuristics behind the moderation badges (issue #149)
|--------------------------------------------------------------------------
|
| Every threshold here is documented on App\Support\TagUsageStats as a display
| judgement, and these tests pin the exact edges: 4-of-5 does not fire, 5-of-5
| does; a 100 % tag below the five-event minimum does not fire at all; zero
| usages never read as "too rare" because a fresh tag is a plan, not a corpse.
|
*/

function taggedEvent(Tag $tag, array $attributes = []): MeetupEvent
{
    $event = MeetupEvent::factory()->create($attributes);
    $event->attachTag($tag);

    return $event;
}

/**
 * One host meetup for a whole test's events.
 *
 * Not stylistic: the nested factories draw from faker's shared unique() pool
 * (CountryFactory picks a unique index out of 48, MeetupFactory burns two unique
 * numbers per row, and all of them share the numberBetween pool). Creating a
 * fresh city-country chain per event exhausts that pool once a file creates
 * enough rows in one process.
 */
function eventHost(): Meetup
{
    $country = Country::factory()->create();
    $city = City::factory()->create(['country_id' => $country->id]);

    return Meetup::factory()->create(['city_id' => $city->id]);
}

it('counts a tag usages across every taggable type', function () {
    $tag = Tag::factory()->create(['type' => 'meetup_event']);
    $host = eventHost();

    taggedEvent($tag, ['meetup_id' => $host->id]);
    taggedEvent($tag, ['meetup_id' => $host->id]);

    expect(TagUsageStats::load()->usageCount($tag))->toBe(2);
});

it('shows zero for a tag nothing carries', function () {
    $tag = Tag::factory()->create(['type' => 'meetup_event']);

    $stats = TagUsageStats::load();

    expect($stats->usageCount($tag))->toBe(0)
        ->and($stats->isTooBroad($tag))->toBeFalse()
        ->and($stats->isTooRare($tag))->toBeFalse();
});

it('flags too broad strictly above 80 percent of the events', function () {
    $broad = Tag::factory()->create(['type' => 'meetup_event']);
    $edge = Tag::factory()->create(['type' => 'meetup_event']);

    $host = eventHost();

    // Five events exist. $edge is on four of them — exactly 80 %, which must
    // NOT fire; $broad is on all five.
    MeetupEvent::factory()->count(5)->sequence(
        ['meetup_id' => $host->id, 'created_at' => now()->subMonths(6)],
        ['meetup_id' => $host->id, 'created_at' => now()->subMonths(6)],
        ['meetup_id' => $host->id, 'created_at' => now()->subMonths(6)],
        ['meetup_id' => $host->id, 'created_at' => now()->subMonths(6)],
        ['meetup_id' => $host->id, 'created_at' => now()->subMonths(6)],
    )->create();

    $events = MeetupEvent::query()->orderBy('id')->get();
    $events->take(4)->each(fn (MeetupEvent $event) => $event->attachTag($edge));
    $events->each(fn (MeetupEvent $event) => $event->attachTag($broad));

    $stats = TagUsageStats::load();

    expect($stats->isTooBroad($edge))->toBeFalse()
        ->and($stats->eventSharePercent($edge))->toBe(80)
        ->and($stats->isTooBroad($broad))->toBeTrue()
        ->and($stats->eventSharePercent($broad))->toBe(100);
});

it('does not flag too broad below the five-event minimum', function () {
    // Three events, all tagged: 100 % — but a young community must not be told
    // its vocabulary is degenerate.
    $tag = Tag::factory()->create(['type' => 'meetup_event']);
    $host = eventHost();

    MeetupEvent::factory()->count(3)->create(['meetup_id' => $host->id])
        ->each(fn (MeetupEvent $event) => $event->attachTag($tag));

    expect(TagUsageStats::load()->isTooBroad($tag))->toBeFalse();
});

it('flags too rare when the only usages are older than the window', function () {
    $tag = Tag::factory()->create(['type' => 'meetup_event']);
    $host = eventHost();

    taggedEvent($tag, ['meetup_id' => $host->id, 'created_at' => now()->subMonths(13)]);
    taggedEvent($tag, ['meetup_id' => $host->id, 'created_at' => now()->subMonths(14)]);

    $stats = TagUsageStats::load();

    expect($stats->usageCount($tag))->toBe(2)
        ->and($stats->recentCount($tag))->toBe(0)
        ->and($stats->isTooRare($tag))->toBeTrue();
});

it('does not flag too rare with a second usage inside the window', function () {
    // The threshold is "<2 recent usages": one recent use still fires, two clear it.
    $tag = Tag::factory()->create(['type' => 'meetup_event']);
    $host = eventHost();

    taggedEvent($tag, ['meetup_id' => $host->id, 'created_at' => now()->subMonths(13)]);
    taggedEvent($tag, ['meetup_id' => $host->id, 'created_at' => now()->subMonth()]);
    taggedEvent($tag, ['meetup_id' => $host->id, 'created_at' => now()->subWeek()]);

    $stats = TagUsageStats::load();

    expect($stats->recentCount($tag))->toBe(2)
        ->and($stats->isTooRare($tag))->toBeFalse();
});

it('counts a one-year-old event as recent and an older one as not', function () {
    $tag = Tag::factory()->create(['type' => 'meetup_event']);
    $host = eventHost();

    taggedEvent($tag, ['meetup_id' => $host->id, 'created_at' => now()->subMonths(11)]);
    taggedEvent($tag, ['meetup_id' => $host->id, 'created_at' => now()->subMonths(12)->subWeek()]);

    expect(TagUsageStats::load()->recentCount($tag))->toBe(1);
});

it('does not flag too rare for a fresh tag nobody used yet', function () {
    $tag = Tag::factory()->create(['type' => 'meetup_event']);

    expect(TagUsageStats::load()->isTooRare($tag))->toBeFalse();
});

it('does not flag too broad for a tag of another type even at full coverage', function () {
    // The ratio is about events of type meetup_event; a library tag hanging off
    // every library item is a different question this badge does not ask.
    $tag = Tag::factory()->create(['type' => 'library_item']);
    $host = eventHost();

    MeetupEvent::factory()->count(6)->create(['meetup_id' => $host->id])
        ->each(fn (MeetupEvent $event) => $event->attachTag($tag));

    expect(TagUsageStats::load()->isTooBroad($tag))->toBeFalse();
});
