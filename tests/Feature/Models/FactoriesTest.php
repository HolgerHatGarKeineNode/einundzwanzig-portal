<?php

use App\Models\BitcoinEvent;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Episode;
use App\Models\Lecturer;
use App\Models\Library;
use App\Models\LibraryItem;
use App\Models\LoginKey;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Participant;
use App\Models\Podcast;
use App\Models\ProjectProposal;
use App\Models\Registration;
use App\Models\SelfHostedService;
use App\Models\Tag;
use App\Models\TwitterAccount;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Model;

it('creates a valid persisted record via the factory', function (string $modelClass): void {
    /** @var Model $model */
    $model = $modelClass::factory()->create();

    expect($model)
        ->toBeInstanceOf($modelClass)
        ->and($model->getKey())->not->toBeNull()
        ->and($model->exists)->toBeTrue();

    expect($modelClass::query()->whereKey($model->getKey())->exists())->toBeTrue();
})->with([
    'User' => User::class,
    'Country' => Country::class,
    'City' => City::class,
    'Lecturer' => Lecturer::class,
    'Category' => Category::class,
    'Course' => Course::class,
    'CourseEvent' => CourseEvent::class,
    'Meetup' => Meetup::class,
    'MeetupEvent' => MeetupEvent::class,
    'BitcoinEvent' => BitcoinEvent::class,
    'Library' => Library::class,
    'LibraryItem' => LibraryItem::class,
    'Episode' => Episode::class,
    'Podcast' => Podcast::class,
    'ProjectProposal' => ProjectProposal::class,
    'Vote' => Vote::class,
    'TwitterAccount' => TwitterAccount::class,
    'SelfHostedService' => SelfHostedService::class,
    'Registration' => Registration::class,
    'Participant' => Participant::class,
    'LoginKey' => LoginKey::class,
    'Tag' => Tag::class,
]);

it('skips the App\\Models\\Team factory since Laravel Jetstream is not installed', function (): void {
    expect(class_exists('Laravel\\Jetstream\\Team'))->toBeFalse();
})->skip(class_exists('Laravel\\Jetstream\\Team'), 'Jetstream installed — Team factory should be tested in the main dataset.');
