<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Tag;
use App\Models\Venue;
use Database\Seeders\TagSeeder;
use Livewire\Livewire;

function venueInCountry(string $code): Venue
{
    $country = Country::factory()->create(['code' => $code]);
    $city = City::factory()->create(['country_id' => $country->id]);

    return Venue::factory()->create(['city_id' => $city->id]);
}

function courseEventTag(string $german): Tag
{
    return Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === $german);
}

beforeEach(function () {
    $this->seed(TagSeeder::class);
    $this->user = actingAsUser();
    $this->user->update(['timezone' => 'Europe/Berlin', 'is_lecturer' => true]);
});

function fillCourseEvent($test, Venue $venue): object
{
    return $test
        ->set('fromDate', now()->addWeek()->format('Y-m-d'))
        ->set('fromTime', '09:00')
        ->set('toDate', now()->addWeek()->format('Y-m-d'))
        ->set('toTime', '12:00')
        ->set('venue_id', $venue->id)
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
    $venue = venueInCountry('de');
    $workshop = courseEventTag('Workshop');

    fillCourseEvent(Livewire::test('courses.create-edit-events', ['course' => $course]), $venue)
        ->set('tagIds', [$workshop->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(CourseEvent::query()->latest('id')->first()->tags->pluck('id')->all())
        ->toBe([$workshop->id]);
});

it('loads existing tags when editing a course event', function () {
    $course = Course::factory()->create(['created_by' => $this->user->id]);
    $venue = venueInCountry('de');
    $event = CourseEvent::factory()->create(['course_id' => $course->id, 'venue_id' => $venue->id]);
    $tag = courseEventTag('Vortrag');
    $event->attachTag($tag);

    Livewire::test('courses.create-edit-events', ['course' => $course, 'event' => $event])
        ->assertSet('tagIds', [$tag->id]);
});

it('requires a tag for a czech venue but not a german one', function () {
    $course = Course::factory()->create(['created_by' => $this->user->id]);

    fillCourseEvent(
        Livewire::test('courses.create-edit-events', ['course' => $course]),
        venueInCountry('cz')
    )->set('tagIds', [])->call('save')->assertHasErrors('tagIds');

    fillCourseEvent(
        Livewire::test('courses.create-edit-events', ['course' => $course]),
        venueInCountry('de')
    )->set('tagIds', [])->call('save')->assertHasNoErrors();
});
