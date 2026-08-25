<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Livewire\Livewire;

function cityInCountry(string $code): City
{
    $country = Country::factory()->create(['code' => $code]);

    return City::factory()->create(['country_id' => $country->id]);
}

function courseEventTag(string $german): Tag
{
    return Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === $german);
}

beforeEach(function () {
    $this->seed(TagSeeder::class);
    $this->user = actingAsUser();
    // `is_lecturer` stand hier mit, solange CourseEventPolicy::create() es abfragte.
    // Es gatet nichts mehr; was den Zugang zum Formular entscheidet, ist die
    // Ersteller-Zugehoerigkeit des Kurses bzw. des Termins.
    $this->user->update(['timezone' => 'Europe/Berlin']);
});

function fillCourseEvent($test, City $city): object
{
    return $test
        ->set('fromDate', now()->addWeek()->format('Y-m-d'))
        ->set('fromTime', '09:00')
        ->set('toDate', now()->addWeek()->format('Y-m-d'))
        ->set('toTime', '12:00')
        ->set('city_id', $city->id)
        ->set('location', 'Teststraße 1')
        ->set('link', 'https://example.com');
}

it('renders the picker in the course event form', function () {
    $course = Course::factory()->create(['created_by' => $this->user->id]);

    Livewire::test('courses.create-edit-events', ['course' => $course])
        ->assertOk()
        ->assertSeeLivewire('tags.picker');
});

it('saves tags on a course event', function () {
    $course = Course::factory()->create(['created_by' => $this->user->id]);
    $city = cityInCountry('de');
    $workshop = courseEventTag('Workshop');

    fillCourseEvent(Livewire::test('courses.create-edit-events', ['course' => $course]), $city)
        ->set('tagIds', [$workshop->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(CourseEvent::query()->latest('id')->first()->tags->pluck('id')->all())
        ->toBe([$workshop->id]);
});

it('loads existing tags when editing a course event', function () {
    $course = Course::factory()->create(['created_by' => $this->user->id]);
    $city = cityInCountry('de');
    $event = CourseEvent::factory()->create(['course_id' => $course->id, 'city_id' => $city->id]);
    $tag = courseEventTag('Vortrag');
    $event->attachTag($tag);

    Livewire::test('courses.create-edit-events', ['course' => $course, 'event' => $event])
        ->assertSet('tagIds', [$tag->id]);
});

it('requires a tag for a czech city but not a german one', function () {
    $course = Course::factory()->create(['created_by' => $this->user->id]);

    fillCourseEvent(
        Livewire::test('courses.create-edit-events', ['course' => $course]),
        cityInCountry('cz')
    )->set('tagIds', [])->call('save')->assertHasErrors('tagIds');

    fillCourseEvent(
        Livewire::test('courses.create-edit-events', ['course' => $course]),
        cityInCountry('de')
    )->set('tagIds', [])->call('save')->assertHasNoErrors();
});
