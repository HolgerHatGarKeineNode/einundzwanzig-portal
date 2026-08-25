<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\Region;
use App\Support\RegionRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Das Regionssegment nimmt ein bis drei alphanumerische Zeichen — und kapert dabei nichts.
 *
 * P4 hatte es auf `[a-z]{2}` festgenagelt. ISO 3166-2 gibt das nicht her: Oesterreich
 * fuehrt Ziffern (`AT-1`…`AT-9`), Grossbritannien, Belgien und Lettland drei Zeichen
 * (`eng`, `bru`, `011`), Frankreich ebenso (`ara`, `bfc`). Auf Produktion standen dadurch
 * 13 franzoesische Regionen in der Datenbank, zu denen keine einzige URL fuehrte.
 *
 * Die zweite Haelfte dieses Tests ist die wichtigere: ein weiteres Muster kann fremde
 * URLs schlucken. Deshalb steht neben jedem „nimmt an" ein „laesst durch" — gemessen am
 * aufgeloesten ROUTENNAMEN, nicht am Statuscode. Ein 200 sagt nicht, WELCHE Route
 * geantwortet hat, und genau das ist hier die Frage.
 */
function regionUrlLandsOn(string $url): ?string
{
    $request = Request::create($url, 'GET');

    try {
        return Route::getRoutes()->match($request)->getName();
    } catch (NotFoundHttpException) {
        return null;
    }
}

it('accepts one, two and three character region segments', function (string $code) {
    expect(regionUrlLandsOn("/de/{$code}/meetups"))->toBe('meetups.index-region')
        ->and(regionUrlLandsOn("/de/{$code}/map"))->toBe('meetups.map-region')
        ->and(regionUrlLandsOn("/de/{$code}/cities"))->toBe('cities.index-region');
})->with([
    'Oesterreich, eine Ziffer' => '9',
    'Deutschland, zwei Buchstaben' => 'by',
    'Frankreich, drei Buchstaben' => 'ara',
    'Lettland, drei Ziffern' => '011',
    'Kolumbien, gemischt' => 'dc',
]);

it('rejects a fourth character, so the segment cannot swallow whole words', function () {
    expect(regionUrlLandsOn('/de/abcd/meetups'))->toBeNull();
});

it('rejects uppercase, so no second URL exists for the same region', function () {
    expect(regionUrlLandsOn('/de/BY/meetups'))->toBeNull();
});

/**
 * Die eigentliche Kollisionsprobe: jede kurze, feste URL der Landesgruppe muss weiterhin
 * auf ihrer eigenen Route landen, nicht auf einer Regionsroute.
 */
it('leaves every neighbouring route on its own name', function (string $url, ?string $expected) {
    expect(regionUrlLandsOn($url))->toBe($expected);
})->with([
    'Landesliste Meetups' => ['/de/meetups', 'meetups.index'],
    'Landesliste Staedte' => ['/de/cities', 'cities.index'],
    'Landeskarte' => ['/de/map', 'meetups.map'],
    'alle Meetups' => ['/de/all-meetups', 'meetups.index-all'],
    'Weltkarte' => ['/de/map-world', 'meetups.map-world'],
    'Kursliste' => ['/de/courses', 'courses.index'],
    'Referentenliste' => ['/de/lecturers', 'lecturers.index'],
    'Serviceliste' => ['/de/services', 'services.index'],
    'Tag-Moderation' => ['/de/tags/moderation', 'tags.moderation'],
    'Meetup-Landingpage' => ['/de/meetup/bitcoin-muenchen', 'meetups.landingpage'],
    'Kurs-Landingpage' => ['/de/course/7', 'courses.landingpage'],
    'Service-Landingpage' => ['/de/service/umbrel', 'services.landingpage'],
    'Kurstermin' => ['/de/course/7/event/3', 'courses.landingpage-event'],
    'Meetup-Termin' => ['/de/meetup/bitcoin-muenchen/event/3', 'meetups.landingpage-event'],
    'alter ICS-Pfad' => ['/de/meetup/stream-calendar', 'ics-meetup'],
]);

/**
 * Und der Beweis, dass die neue Weite nicht nur die Route trifft, sondern die Seite auch
 * die richtige Region auflaest — ein 200 auf einer Route, die die Region ignoriert,
 * waere kein Fortschritt.
 */
it('resolves a three character region end to end', function () {
    $country = Country::factory()->create(['code' => 'fr', 'name' => 'Frankreich']);
    $region = Region::factory()->create([
        'country_id' => $country->id,
        'code' => 'ara',
        'name' => 'Auvergne-Rhône-Alpes',
    ]);
    $city = City::factory()->create(['country_id' => $country->id, 'region_id' => $region->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'name' => 'Bitcoin Lyon']);

    $this->get('/fr/ara/meetups')
        ->assertSuccessful()
        ->assertSee('Bitcoin Lyon');

    // Gegenprobe: ein Code, den es in diesem Land nicht gibt, bleibt ein 404 — die
    // weitere Regex macht aus einem unbekannten Segment keine leere Liste.
    $this->get('/fr/xyz/meetups')->assertNotFound();

    expect($meetup->fresh()->city->region_id)->toBe($region->id);
});

it('keeps the three region routes on one shared pattern', function () {
    $muster = collect(['meetups.index-region', 'meetups.map-region', 'cities.index-region'])
        ->map(fn (string $name) => Route::getRoutes()->getByName($name)?->wheres['region'] ?? null)
        ->unique()
        ->values();

    expect($muster->all())->toBe([RegionRoutes::SEGMENT_PATTERN]);
});
