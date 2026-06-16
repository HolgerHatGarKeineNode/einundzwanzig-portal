<?php

use App\Http\Controllers\Api\BtcMapCommunityController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseEventController;
use App\Http\Controllers\Api\LecturerController;
use App\Http\Controllers\Api\MeetupController;
use App\Http\Controllers\Api\MeetupEventController;
use App\Http\Controllers\Api\MeetupMapController;
use App\Http\Controllers\Api\NostrPlebController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VenueController;
use App\Http\Controllers\LnurlAuthController;
use App\Http\Controllers\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1'])
    ->as('api.')
    ->group(function () {
        Route::resource('countries', CountryController::class)->only(['index']);
        Route::get('meetup/ical', [MeetupController::class, 'ical'])->name('api.meetup.ical');
        Route::resource('meetup', MeetupController::class)->only(['index']);
        Route::resource('lecturers', LecturerController::class)->only(['index', 'show']);
        Route::resource('courses', CourseController::class)
            ->only(['index', 'show']);
        Route::resource('cities', CityController::class)->only(['index']);
        Route::resource('venues', VenueController::class)->only(['index']);
        Route::get('nostrplebs', NostrPlebController::class);
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
        Route::get('user', UserController::class)->name('user');
        Route::patch('user', [UserController::class, 'update'])->name('user.update');

        Route::post('courses', [CourseController::class, 'store'])
            ->name('courses.store');
        Route::patch('courses/{course}', [CourseController::class, 'update'])
            ->name('courses.update');
        Route::post('courses/{course}/logo', [CourseController::class, 'uploadLogo'])
            ->name('courses.logo');

        Route::get('course-events', [CourseEventController::class, 'index'])
            ->name('course-events.index');
        Route::post('course-events', [CourseEventController::class, 'store'])
            ->name('course-events.store');
        Route::patch('course-events/{courseEvent}', [CourseEventController::class, 'update'])
            ->name('course-events.update');

        Route::post('lecturers', [LecturerController::class, 'store'])->name('lecturers.store');
        Route::patch('lecturers/{lecturer}', [LecturerController::class, 'update'])->name('lecturers.update');
        Route::post('lecturers/{lecturer}/avatar', [LecturerController::class, 'uploadAvatar'])->name('lecturers.avatar');
        Route::get('my-lecturers', [LecturerController::class, 'mine'])->name('lecturers.mine');
        Route::get('my-lecturers/{lecturer}', [LecturerController::class, 'mineShow'])->name('lecturers.mine.show');

        Route::post('venues', [VenueController::class, 'store'])->name('venues.store');
        Route::patch('venues/{venue}', [VenueController::class, 'update'])->name('venues.update');
        Route::get('my-venues', [VenueController::class, 'mine'])->name('venues.mine');
        Route::get('my-venues/{venue}', [VenueController::class, 'mineShow'])->name('venues.mine.show');

        Route::post('cities', [CityController::class, 'store'])->name('cities.store');
        Route::patch('cities/{city}', [CityController::class, 'update'])->name('cities.update');
        Route::get('my-cities', [CityController::class, 'mine'])->name('cities.mine');
        Route::get('my-cities/{city}', [CityController::class, 'mineShow'])->name('cities.mine.show');

        Route::post('meetup', [MeetupController::class, 'store'])->name('meetup.store');
        Route::patch('meetup/{meetup}', [MeetupController::class, 'update'])->name('meetup.update');
        Route::post('meetup/{meetup}/logo', [MeetupController::class, 'uploadLogo'])->name('meetup.logo');
        Route::get('my-meetups', [MeetupController::class, 'mine'])->name('meetup.mine');
        Route::post('my-meetups/{meetup:slug}', [MeetupController::class, 'addToMine'])->name('meetup.mine.add');
        Route::delete('my-meetups/{meetup:slug}', [MeetupController::class, 'removeFromMine'])->name('meetup.mine.remove');
        Route::get('my-meetups/{meetup}', [MeetupController::class, 'mineShow'])->name('meetup.mine.show');

        Route::post('meetup-events', [MeetupEventController::class, 'store'])->name('meetup-events.store');
        Route::patch('meetup-events/{meetupEvent}', [MeetupEventController::class, 'update'])->name('meetup-events.update');
        Route::get('my-meetup-events', [MeetupEventController::class, 'mine'])->name('meetup-events.mine');
        Route::get('my-meetup-events/{meetupEvent}', [MeetupEventController::class, 'mineShow'])->name('meetup-events.mine.show');
        Route::get('meetup-events/{meetupEvent}/rsvp', [MeetupEventController::class, 'rsvpStatus'])->name('meetup-events.rsvp.show');
        Route::post('meetup-events/{meetupEvent}/rsvp', [MeetupEventController::class, 'rsvp'])->name('meetup-events.rsvp');
    });

Route::get('/lnurl-auth-callback', [LnurlAuthController::class, 'callback'])
    ->name('auth.ln.callback');

// NIP-55 signer callback (e.g. Amber) for the mobile auth flow.
Route::get('/nostr-login-callback', [MobileAuthController::class, 'nostrCallback'])
    ->middleware('throttle:30,1')
    ->name('auth.nostr.callback');

// Token exchange for the mobile app: trades a NIP-55-signed login event
// for a Sanctum personal access token (used when the signer callback
// opens the app directly via a verified App Link).
Route::post('/mobile/token', [MobileAuthController::class, 'token'])
    ->middleware('throttle:30,1')
    ->name('auth.mobile.token');

// Logout for the mobile app: revokes the personal access token that
// authenticated this request, so a local "disconnect" in the app also
// invalidates the token server-side.
Route::delete('/mobile/token', [MobileAuthController::class, 'revoke'])
    ->middleware(['auth:sanctum', 'throttle:30,1'])
    ->name('auth.mobile.token.revoke');

Route::post('/check-auth-error', [LnurlAuthController::class, 'checkError'])
    ->name('auth.check-error');
