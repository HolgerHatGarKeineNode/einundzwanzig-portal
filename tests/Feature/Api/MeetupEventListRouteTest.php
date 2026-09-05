<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use Database\Seeders\TagSeeder;

/*
|--------------------------------------------------------------------------
| Issue #57 — the bare list path exists as its own route, and is documented
|--------------------------------------------------------------------------
|
| `GET /api/meetup-events` used to be the optional-parameter half of a single
| `meetup-events/{date?}` route. Scramble collapses that: it rewrites the URI with
| `Str::replace('?}', '}', …)` before building the operation, so the document held
| exactly one GET — `/meetup-events/{date}` — and the path consumers actually call had
| no operation at all, annotation or not.
|
| The route is therefore split, and the bare path has its own thin controller method:
| a shared method would drag its `#[PathParameter(name: 'date')]` onto a path with no
| `{date}` placeholder, which is invalid OpenAPI.
|
| Two things have to hold, and each has a test below:
|
|  1. Nothing changed for existing consumers. The split is a documentation fix, not a
|     payload change — same status, same keys, same values as the dated variant.
|  2. The bare path is in the generated document, with `locale` and without a `date`
|     path parameter. That is the whole point of the split; without it the second test
|     goes red.
|
*/

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $city->id]);
});

it('answers the bare list path with the unchanged payload of the dated one', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'title' => 'Einsteigerabend',
        'start' => now()->addWeek()->setTime(19, 0),
        'end' => now()->addWeek()->setTime(22, 0),
    ]);

    $bare = $this->getJson('/api/meetup-events')->assertOk()->json();
    $dated = $this->getJson('/api/meetup-events/'.now()->addWeek()->format('Y-m-d'))
        ->assertOk()
        ->json();

    $bareRow = collect($bare)->firstWhere('id', $event->id);
    $datedRow = collect($dated)->firstWhere('id', $event->id);

    /*
     * The published contract of this endpoint, key by key and in order. Spelled out
     * rather than derived from the response, because a list derived from what the
     * endpoint currently emits would agree with any change it ever makes — including
     * the one this test exists to catch. Top level stays a bare array: this endpoint
     * has never had a `data` wrapper and gaining one would break every consumer.
     */
    expect($bare)->toBeArray()
        ->and(array_keys($bareRow))->toBe([
            'id',
            'title',
            'start',
            'end',
            'start_iso',
            'end_iso',
            'location',
            'osm_type',
            'osm_id',
            'osm_name',
            'osm_address',
            'osm_lat',
            'osm_lon',
            'description',
            // `link` is the deprecated single link and `links` the list that replaced
            // it (issue #70). Both are part of the contract until the mobile client
            // has moved over — see the controller's comment on the pair.
            'link',
            'links',
            'tags',
            'attendees',
            'might_attendees',
            'meetup.name',
            'meetup.portalLink',
            'meetup.url',
            'meetup.country',
            'meetup.city',
            'meetup.longitude',
            'meetup.latitude',
            'meetup.twitter_username',
            'meetup.website',
            'meetup.simplex',
            'meetup.signal',
            'meetup.nostr',
            'meetup.logo',
            'meetup.rsvp_enabled',
        ])
        ->and($bareRow['title'])->toBe('Einsteigerabend')
        // Both routes reach the same code, and the value comparison says so for every
        // field at once — a divergence between the two halves of the former single
        // route is exactly what a split can introduce.
        ->and($bareRow)->toBe($datedRow);
});

it('honours ?locale= on the bare list path', function () {
    $this->seed(TagSeeder::class);

    // Looked up here rather than through the helper in MeetupEventTagsApiTest: a
    // function declared in another test file only exists when that file happens to be
    // loaded in the same run, which a filtered run cannot promise.
    $tag = Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === 'Vortrag');

    $event = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);
    $event->attachTag($tag);

    $row = collect(
        $this->getJson('/api/meetup-events?locale=cs')->assertOk()->json(),
    )->firstWhere('id', $event->id);

    expect($row['tags'][0]['name'])->toBe('Přednáška')
        ->and($row['tags'][0]['locale'])->toBe('cs');
});

it('documents the bare list path with locale and without a date path parameter', function () {
    $document = $this->get(route('scramble.docs.document'))
        ->assertSuccessful()
        ->json();

    $operation = $document['paths']['/meetup-events']['get'] ?? null;

    // The operation itself first: before the split this key did not exist, and every
    // assertion below would have failed on a null instead of naming the real problem.
    expect($operation)->not->toBeNull();

    $parameters = collect($operation['parameters'] ?? []);

    expect($parameters->firstWhere('name', 'locale')['in'] ?? null)->toBe('query')
        /*
         * No path parameter at all. A `date` parameter here would be the failure mode
         * of the cheaper fix — splitting the route but leaving both halves on the
         * method that carries `#[PathParameter(name: 'date')]`. The document would
         * then declare a path parameter for a placeholder the path does not have,
         * which is invalid OpenAPI and misleads every generated client.
         */
        ->and($parameters->where('in', 'path')->pluck('name')->all())->toBe([]);

    // The dated variant is untouched and keeps carrying both.
    $datedParameters = collect($document['paths']['/meetup-events/{date}']['get']['parameters'] ?? []);

    expect($datedParameters->firstWhere('name', 'date')['in'] ?? null)->toBe('path')
        ->and($datedParameters->firstWhere('name', 'locale')['in'] ?? null)->toBe('query');
});
