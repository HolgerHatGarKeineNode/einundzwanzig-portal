<?php

use App\Models\BitcoinEvent;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Venue;
use App\Services\Osm\NominatimClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    NominatimClient::resetThrottle();
    Cache::flush();

    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id, 'name' => 'Nürnberg']);
});

function osmHit(string $name, float $importance = 0.4, int $id = 1): array
{
    return [
        'osm_type' => 'node',
        'osm_id' => $id,
        'name' => $name,
        'display_name' => "{$name}, Hauptstraße 1, Nürnberg",
        'lat' => '49.4521',
        'lon' => '11.0767',
        'importance' => $importance,
    ];
}

it('does nothing when there are no venues', function () {
    $this->artisan('venues:match-osm --dry-run --fast')
        ->expectsOutputToContain('Keine Venues')
        ->assertSuccessful();
});

it('accepts a clear single match', function () {
    Venue::factory()->create(['name' => 'Bitcoin Bar', 'city_id' => $this->city->id]);
    Http::fake(['*' => Http::response([osmHit('Bitcoin Bar')])]);

    $this->artisan('venues:match-osm --dry-run --fast')->assertSuccessful();
});

it('writes the match onto the events of that venue', function () {
    $venue = Venue::factory()->create(['name' => 'Bitcoin Bar', 'city_id' => $this->city->id]);
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);
    $bitcoinEvent = BitcoinEvent::factory()->create(['venue_id' => $venue->id]);

    Http::fake(['*' => Http::response([osmHit('Bitcoin Bar')])]);

    $this->artisan('venues:match-osm --fast')->assertSuccessful();

    expect($event->fresh()->osm_id)->toBe(1)
        ->and($event->fresh()->osm_name)->toBe('Bitcoin Bar')
        ->and($event->fresh()->osm_lat)->not->toBeNull()
        ->and($bitcoinEvent->fresh()->osm_id)->toBe(1);
});

it('changes nothing on a dry run', function () {
    $venue = Venue::factory()->create(['name' => 'Bitcoin Bar', 'city_id' => $this->city->id]);
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);

    Http::fake(['*' => Http::response([osmHit('Bitcoin Bar')])]);

    $this->artisan('venues:match-osm --dry-run --fast')->assertSuccessful();

    expect($event->fresh()->osm_id)->toBeNull();
});

it('refuses to write when two candidates are almost equally good', function () {
    // The real doubtful case: NEITHER candidate matches exactly, and they are similar
    // to each other. Picking one at random is how visitors end up at the wrong door.
    // Both score 0.690 against the venue name — inside the grey band, and tied.
    $venue = Venue::factory()->create(['name' => 'Treffpunkt', 'city_id' => $this->city->id]);
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);

    Http::fake(['*' => Http::response([
        osmHit('Treffpunkt am Markt', 0.5, 1),
        osmHit('Treffpunkt am Hafen', 0.49, 2),
    ])]);

    $this->artisan('venues:match-osm --fast')->assertFailed();

    expect($event->fresh()->osm_id)->toBeNull();
});

it('accepts a near-exact name even when a similar runner-up exists', function () {
    // Measured against production: "Messe Innsbruck" matches OSM's "Innsbruck Messe"
    // almost perfectly, and so does the runner-up — the distance rule alone threw away
    // an obviously correct match.
    $venue = Venue::factory()->create(['name' => 'Cafe Central', 'city_id' => $this->city->id]);
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);

    Http::fake(['*' => Http::response([
        osmHit('Cafe Central', 0.5, 1),
        osmHit('Cafe Centrale', 0.49, 2),
    ])]);

    $this->artisan('venues:match-osm --fast')->assertSuccessful();

    expect($event->fresh()->osm_id)->toBe(1);
});

it('retries without the street when the full query finds nothing', function () {
    // Measured: our street often disagrees with what OSM holds, while the venue itself
    // is well known. Name plus city is the query a human would type.
    Venue::factory()->create([
        'name' => 'Lässerhof',
        'street' => 'Hofweg 2',
        'city_id' => $this->city->id,
    ]);

    Http::fake(fn ($request) => str_contains(urldecode($request->url()), 'Hofweg')
        ? Http::response([])
        : Http::response([osmHit('Lässerhof')]));

    $this->artisan('venues:match-osm --dry-run --fast')->assertSuccessful();

    Http::assertSentCount(2);
});

it('calls the attempt off below the threshold and writes nothing', function () {
    Venue::factory()->create(['name' => 'Hinterzimmer Müller', 'city_id' => $this->city->id]);
    $venue = Venue::factory()->create(['name' => 'Bitcoin Bar', 'city_id' => $this->city->id]);
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);

    // Nothing resolves.
    Http::fake(['*' => Http::response([])]);

    $this->artisan('venues:match-osm --fast')
        ->expectsOutputToContain('unter der Schwelle')
        ->assertFailed();

    expect($event->fresh()->osm_id)->toBeNull();
});

it('skips names that describe an arrangement rather than a place', function () {
    Venue::factory()->create(['name' => 'TBA', 'city_id' => $this->city->id]);
    Http::fake(['*' => Http::response([osmHit('Irgendwas')])]);

    $this->artisan('venues:match-osm --dry-run --fast')->assertSuccessful();

    // A non-place must not cost a request at all.
    Http::assertNothingSent();
});

it('honours a custom threshold', function () {
    Venue::factory()->create(['name' => 'Bitcoin Bar', 'city_id' => $this->city->id]);
    Venue::factory()->create(['name' => 'Unauffindbar', 'city_id' => $this->city->id]);

    Http::fake(fn ($request) => str_contains($request->url(), 'Bitcoin')
        ? Http::response([osmHit('Bitcoin Bar')])
        : Http::response([]));

    // 50 % confident: fails at 70, passes at 50.
    $this->artisan('venues:match-osm --dry-run --fast --threshold=70')->assertFailed();
    $this->artisan('venues:match-osm --dry-run --fast --threshold=50')->assertSuccessful();
});

it('narrows the lookup to the venue country', function () {
    $czech = Country::factory()->create(['code' => 'cz']);
    $praha = City::factory()->create(['country_id' => $czech->id, 'name' => 'Praha']);
    Venue::factory()->create(['name' => 'Bitcoin Bar', 'city_id' => $praha->id]);

    Http::fake(['*' => Http::response([osmHit('Bitcoin Bar')])]);

    $this->artisan('venues:match-osm --dry-run --fast')->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'countrycodes=cz'));
});

it('writes a report a human can act on', function () {
    Venue::factory()->create(['name' => 'Unauffindbar', 'city_id' => $this->city->id]);
    Http::fake(['*' => Http::response([])]);

    $this->artisan('venues:match-osm --dry-run --fast')->assertFailed();

    $path = base_path('docs/plans/2026-08-17T1505-events-modell-tags-osm/osm-match-report.md');

    if (is_dir(dirname($path))) {
        expect(file_get_contents($path))
            ->toContain('Trefferquote')
            ->toContain('Unauffindbar');
    }
})->skip(fn (): bool => ! is_dir(base_path('docs/plans/2026-08-17T1505-events-modell-tags-osm')), 'Plan-Ordner fehlt');

it('does not let placeholder venues drag the rate down', function () {
    // Three TBAs plus one clean match must count as 100 %, not 25 %.
    foreach (['TBA', 'online', 'wird bekannt gegeben'] as $placeholder) {
        Venue::factory()->create(['name' => $placeholder, 'city_id' => $this->city->id]);
    }
    $venue = Venue::factory()->create(['name' => 'Bitcoin Bar', 'city_id' => $this->city->id]);
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);

    Http::fake(['*' => Http::response([osmHit('Bitcoin Bar')])]);

    $this->artisan('venues:match-osm --fast --threshold=70')->assertSuccessful();

    expect($event->fresh()->osm_id)->toBe(1);
});

it('does not treat a lone mediocre hit as proof', function () {
    // Measured on production: "Volkshochschule Kassel (Landkreis)" returned exactly one
    // result — "Volkshochschule Hofgeismar", a different town, scoring 0.633. Being the
    // only answer is not evidence of being the right one.
    $venue = Venue::factory()->create([
        'name' => 'Volkshochschule Kassel (Landkreis)',
        'city_id' => $this->city->id,
    ]);
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);

    Http::fake(['*' => Http::response([osmHit('Volkshochschule Hofgeismar')])]);

    $this->artisan('venues:match-osm --fast')->assertFailed();

    expect($event->fresh()->osm_id)->toBeNull();
});

it('still accepts a lone hit when the name matches closely', function () {
    $venue = Venue::factory()->create(['name' => 'Deutsches Hygiene-Museum', 'city_id' => $this->city->id]);
    $course = Course::factory()->create();
    $event = CourseEvent::factory()->create(['venue_id' => $venue->id, 'course_id' => $course->id]);

    Http::fake(['*' => Http::response([osmHit('Deutsches Hygiene-Museum')])]);

    $this->artisan('venues:match-osm --fast')->assertSuccessful();

    expect($event->fresh()->osm_id)->toBe(1);
});

it('exports the confident matches for a data migration', function () {
    Venue::factory()->create(['name' => 'Deutsches Hygiene-Museum', 'city_id' => $this->city->id]);
    Http::fake(['*' => Http::response([osmHit('Deutsches Hygiene-Museum')])]);

    $path = sys_get_temp_dir().'/osm-matches-'.getmypid().'.json';

    $this->artisan("venues:match-osm --dry-run --fast --export-matches={$path}")->assertSuccessful();

    $exported = json_decode((string) file_get_contents($path), true);

    expect($exported)->toHaveCount(1)
        ->and($exported[0]['osm_id'])->toBe(1)
        // The venue name travels along so the migration can refuse a row that has changed.
        ->and($exported[0]['venue_name'])->toBe('Deutsches Hygiene-Museum');

    unlink($path);
});
