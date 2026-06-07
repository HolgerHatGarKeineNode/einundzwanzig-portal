<?php

use App\Http\Controllers\Api\BindleController;
use App\Http\Controllers\Api\BtcMapCommunityController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseEventController;
use App\Http\Controllers\Api\HighscoreController;
use App\Http\Controllers\Api\LecturerController;
use App\Http\Controllers\Api\MeetupController;
use App\Http\Controllers\Api\MeetupEventController;
use App\Http\Controllers\Api\MeetupMapController;
use App\Http\Controllers\Api\NostrPlebController;
use App\Http\Controllers\Api\VenueController;
use App\Http\Controllers\LnurlAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1'])
    ->as('api.')
    ->group(function () {
        Route::resource('countries', CountryController::class);
        Route::get('meetup/ical', [MeetupController::class, 'ical'])->name('api.meetup.ical');
        Route::resource('meetup', MeetupController::class);
        Route::resource('lecturers', LecturerController::class);
        Route::resource('courses', CourseController::class)
            ->only(['index', 'show']);
        Route::resource('cities', CityController::class);
        Route::resource('venues', VenueController::class);
        Route::get('highscores', [HighscoreController::class, 'index'])->name('highscores.index');
        Route::post('highscores', [HighscoreController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('highscores.store');
        Route::get('nostrplebs', NostrPlebController::class);
        Route::get('bindles', BindleController::class);
        Route::get('meetups', MeetupMapController::class);
        Route::get('meetup-events/{date?}', MeetupEventController::class);
        Route::get('btc-map-communities', BtcMapCommunityController::class);
    });

/*
 * Authenticated write endpoints (Sanctum token auth).
 * Lets a lecturer create/update their own courses and course events
 * programmatically, e.g. to sync events from an external system.
 */
Route::middleware('auth:sanctum')
    ->as('api.')
    ->group(function () {
        Route::post('courses', [CourseController::class, 'store'])
            ->name('courses.store');
        Route::patch('courses/{course}', [CourseController::class, 'update'])
            ->name('courses.update');

        Route::get('course-events', [CourseEventController::class, 'index'])
            ->name('course-events.index');
        Route::post('course-events', [CourseEventController::class, 'store'])
            ->name('course-events.store');
        Route::patch('course-events/{courseEvent}', [CourseEventController::class, 'update'])
            ->name('course-events.update');
    });

Route::get('/lnurl-auth-callback', [LnurlAuthController::class, 'callback'])
    ->name('auth.ln.callback');

Route::post('/check-auth-error', [LnurlAuthController::class, 'checkError'])
    ->name('auth.check-error');
