<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Livewire\Livewire;

function meetupInCountry(string $code, User $owner): Meetup
{
    $country = Country::factory()->create(['code' => $code]);
    $city = City::factory()->create(['country_id' => $country->id]);

    return Meetup::factory()->create(['city_id' => $city->id, 'created_by' => $owner->id]);
}

function eventTag(string $german): Tag
{
    return Tag::query()->where('type', 'meetup_event')->get()
        ->first(fn (Tag $t): bool => $t->getTranslation('name', 'de') === $german);
}

beforeEach(function () {
    $this->seed(TagSeeder::class);
});

it('renders the picker inside the event form', function () {
    $meetup = meetupInCountry('de', actingAsUser());

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->assertOk()
        ->assertSeeLivewire('tags.picker');
});

it('offers only event tags, not the library vocabulary', function () {
    actingAsUser();

    $options = Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->instance()->options;

    expect($options)->toHaveCount(15)
        ->and($options->pluck('type')->unique()->all())->toBe(['meetup_event']);
});

it('sorts featured tags to the front', function () {
    actingAsUser();

    $options = Livewire::test('tags.picker', ['type' => 'meetup_event'])
        ->instance()->options;

    expect($options->take(7)->pluck('featured')->unique()->all())->toBe([true])
        ->and($options->where('featured', true))->toHaveCount(7);
});

it('builds search aliases across all nine locales', function () {
    actingAsUser();

    $picker = Livewire::test('tags.picker', ['type' => 'meetup_event'])->instance();
    $aliases = $picker->aliasesFor(eventTag('Vortrag'));

    // Names and slugs from every locale, so a Czech search hits a German-only tag.
    expect($aliases)->toContain('Vortrag', 'Talk', 'Přednáška', 'Prelekcja')
        ->and($aliases)->toContain('vortrag', 'prednaska');
});

it('saves the picked tags on a new event', function () {
    $meetup = meetupInCountry('de', actingAsUser());
    $talk = eventTag('Vortrag');
    $beginners = eventTag('Einsteiger');

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Café Test')
        ->set('description', 'Ein Test-Event')
        ->set('link', 'https://example.com')
        ->set('tagIds', [$talk->id, $beginners->id])
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->first();

    expect($event->tags->pluck('id')->sort()->values()->all())
        ->toBe(collect([$talk->id, $beginners->id])->sort()->values()->all());
});

it('loads existing tags when editing', function () {
    $meetup = meetupInCountry('de', actingAsUser());
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);
    $tag = eventTag('Workshop');
    $event->attachTag($tag);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSet('tagIds', [$tag->id]);
});

it('requires a tag in czechia but nowhere else', function () {
    $owner = actingAsUser();
    $czech = meetupInCountry('cz', $owner);
    $german = meetupInCountry('de', $owner);

    $fill = fn ($test) => $test
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Místo')
        ->set('description', 'Popis')
        ->set('link', 'https://example.com');

    $fill(Livewire::test('meetups.create-edit-events', ['meetup' => $czech]))
        ->set('tagIds', [])
        ->call('save')
        ->assertHasErrors('tagIds');

    $fill(Livewire::test('meetups.create-edit-events', ['meetup' => $german]))
        ->set('tagIds', [])
        ->call('save')
        ->assertHasNoErrors();
});

it('reads the requirement from the meetup country, not the browsed one', function () {
    $owner = actingAsUser();
    $czech = meetupInCountry('cz', $owner);

    expect(Livewire::test('meetups.create-edit-events', ['meetup' => $czech])
        ->instance()->tagsRequired)->toBeTrue();

    $german = meetupInCountry('de', $owner);

    expect(Livewire::test('meetups.create-edit-events', ['meetup' => $german])
        ->instance()->tagsRequired)->toBeFalse();
});

it('refuses a tag id the user was never offered', function () {
    // A crafted request must not attach someone else's pending suggestion.
    $owner = actingAsUser();
    $meetup = meetupInCountry('de', $owner);

    $stranger = User::factory()->create();
    $secret = Tag::factory()->pending($stranger)->create(['type' => 'meetup_event']);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Café Test')
        ->set('description', 'Ein Test-Event')
        ->set('link', 'https://example.com')
        ->set('tagIds', [$secret->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(MeetupEvent::query()->latest('id')->first()->tags)->toHaveCount(0);
});
