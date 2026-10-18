<?php

use App\Http\Requests\Api\StoreCourseEventRequest;
use App\Http\Requests\Api\StoreLecturerRequest;
use App\Http\Requests\Api\StoreMeetupEventRequest;
use App\Http\Requests\Api\StoreMeetupRequest;
use App\Http\Requests\Api\UpdateCourseEventRequest;
use App\Http\Requests\Api\UpdateLecturerRequest;
use App\Http\Requests\Api\UpdateMeetupEventRequest;
use App\Http\Requests\Api\UpdateMeetupRequest;
use App\Livewire\Forms\ServiceForm;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Issue #164 — a URL longer than 255 characters can be saved
|--------------------------------------------------------------------------
|
| Two limits stood in the way and both are guarded here: the `max:255` rule
| in front of every URL field, and the VARCHAR(255) column behind it. The
| test database is SQLite, which does not enforce a VARCHAR length, so a
| long value saving here proves nothing about the column — the column TYPE
| is asserted instead.
|
*/

function urlOfLength(int $length): string
{
    $prefix = 'https://example.com/?q=';

    return $prefix.str_repeat('a', $length - strlen($prefix));
}

it('stores every user-entered URL column as text', function (string $table, string $column) {
    expect(Schema::getColumnType($table, $column))->toBe('text');
})->with([
    ['meetup_events', 'link'],
    ['course_events', 'link'],
    ['meetups', 'telegram_link'],
    ['meetups', 'webpage'],
    ['lecturers', 'website'],
    ['self_hosted_services', 'url_clearnet'],
    ['self_hosted_services', 'url_onion'],
    ['self_hosted_services', 'url_i2p'],
    ['self_hosted_services', 'url_pkdns'],
    ['webhook_subscriptions', 'url'],
]);

it('accepts a 2048-character URL and rejects 2049 in every API request', function (string $requestClass, string $field) {
    $rules = (new $requestClass)->rules();
    $data = fn (string $url) => $field === 'links.*.url'
        ? ['links' => [['url' => $url]]]
        : [$field => $url];

    expect(Validator::make($data(urlOfLength(2048)), [$field => $rules[$field]])->passes())->toBeTrue()
        ->and(Validator::make($data(urlOfLength(2049)), [$field => $rules[$field]])->errors()->has(str_replace('*', '0', $field)))->toBeTrue();
})->with([
    [StoreMeetupEventRequest::class, 'link'],
    [StoreMeetupEventRequest::class, 'links.*.url'],
    [UpdateMeetupEventRequest::class, 'link'],
    [UpdateMeetupEventRequest::class, 'links.*.url'],
    [StoreCourseEventRequest::class, 'link'],
    [UpdateCourseEventRequest::class, 'link'],
    [StoreMeetupRequest::class, 'telegram_link'],
    [StoreMeetupRequest::class, 'webpage'],
    [UpdateMeetupRequest::class, 'telegram_link'],
    [UpdateMeetupRequest::class, 'webpage'],
    [StoreLecturerRequest::class, 'website'],
    [UpdateLecturerRequest::class, 'website'],
]);

it('accepts a 2048-character URL in every service URL field', function (string $field) {
    $rules = (new ReflectionMethod(ServiceForm::class, 'rules'))->invoke(
        (new ReflectionClass(ServiceForm::class))->newInstanceWithoutConstructor()
    );

    expect(Validator::make([$field => urlOfLength(2048)], [$field => $rules[$field]])->passes())->toBeTrue()
        ->and(Validator::make([$field => urlOfLength(2049)], [$field => $rules[$field]])->fails())->toBeTrue();
})->with(['url_clearnet', 'url_onion', 'url_i2p', 'url_pkdns']);

it('saves an event link longer than 255 characters from the event form', function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addWeek()]);
    $longUrl = urlOfLength(600);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->set('links', [['url' => $longUrl, 'label' => 'BitcoinWalk']])
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->links)->toBe([['url' => $longUrl, 'label' => 'BitcoinWalk']])
        ->and($event->link)->toBe($longUrl);
});

it('still refuses an event link beyond 2048 characters', function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addWeek()]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->set('links', [['url' => urlOfLength(2049), 'label' => null]])
        ->call('save')
        ->assertHasErrors('links.0.url');
});

it('saves long contact links from the meetup edit form', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id, 'community' => 'einundzwanzig']);
    $longUrl = urlOfLength(600);
    $simplexLink = 'https://simplex.chat/contact#/?v=2-7&smp='.str_repeat('a', 500);
    $signalLink = 'https://signal.group/#'.str_repeat('b', 400);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('webpage', $longUrl)
        ->set('telegram_link', $longUrl)
        ->set('simplex', $simplexLink)
        ->set('signal', $signalLink)
        ->call('updateMeetup')
        ->assertHasNoErrors();

    $meetup->refresh();

    expect($meetup->webpage)->toBe($longUrl)
        ->and($meetup->telegram_link)->toBe($longUrl)
        ->and($meetup->simplex)->toBe($simplexLink)
        ->and($meetup->signal)->toBe($signalLink);
});

it('limits the Signal link to its 510-character column in the API', function (string $requestClass) {
    $rules = ['signal' => (new $requestClass)->rules()['signal']];

    expect(Validator::make(['signal' => str_repeat('a', 510)], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['signal' => str_repeat('a', 511)], $rules)->fails())->toBeTrue();
})->with([StoreMeetupRequest::class, UpdateMeetupRequest::class]);
