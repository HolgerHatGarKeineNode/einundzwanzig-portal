<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Http\Middleware\Sample;
use Livewire\Volt\Volt;

// Redirect root URL to 'welcome' page
Route::redirect('/', 'welcome');

// Test route that dispatches a job to fetch Nostr profile for user with ID 1426
Route::get('test', function () {
    \App\Jobs\FetchNostrProfileJob::dispatchSync(\App\Models\User::find(1426));
});

// Error page route that aborts with given HTTP status code
Route::get('error/{code}', function ($code) {
    abort($code);
});

/*
 * Commented out routes related to book rental download and display
 * These are currently inactive but can be enabled if needed
 */
/*Route::get('/download-buecherverleih', function (Request $request) {
    $filename = $request->input('filename');
    // Get the file path from the storage folder
    $filePath = storage_path('app/'.$filename);
    dd($filePath);
    // Check if the file exists
    if (!file_exists($filePath)) {
        abort(404);
    }
    // Generate a response with the file for download
    return response()->download($filePath, $filename);
})->name('buecherverleih.download');

Route::middleware([])
    ->get('/buecherverleih', \App\Livewire\BooksForPlebs\BookRentalGuide::class)
    ->name('buecherverleih');*/

// Route for rabbit following helper page - Updated for Livewire v4
Route::livewire('/kaninchenbau', \App\Livewire\Helper\FollowTheRabbit::class)
    ->name('kaninchenbau');

// Generic image handler route that serves images from storage
Route::get('/img/{path}', \App\Http\Controllers\ImageController::class)
    ->where('path', '.*')
    ->name('img');

// Public image handler route for serving public images
Route::get('/img-public/{path}', \App\Http\Controllers\ImageController::class)
    ->where('path', '.*')
    ->name('imgPublic');

// Welcome page route using Volt component
Route::livewire('/welcome', 'welcome')->name('welcome');

// Stream calendar route to download meetup calendar as ICS file
Route::get('stream-calendar', \App\Http\Controllers\DownloadMeetupCalendar::class)
    ->name('ics');

// Dashboard redirect route for authenticated users, redirects to German dashboard
Route::middleware(['auth'])
    ->get('dashboard', function () {
        return redirect('/de/dashboard'); // Redirect to German dashboard
    });

// Country-specific routes group (optional country code parameter)
Route::middleware([])
    ->prefix('/{country:code?}')
    ->group(function () {
        Route::livewire('/dashboard', 'dashboard')->name('dashboard');
    });

// Country-specific routes group with mandatory country code parameter
Route::middleware([])
    ->prefix('/{country:code}')
    ->group(function () {
        /* OLD URLS - redirects for legacy URLs */
        // Redirect old meetup calendar route to new one
        Route::get('meetup/stream-calendar', \App\Http\Controllers\DownloadMeetupCalendar::class)
            ->name('ics');
        // Redirect old meetup overview URL to new meetups page
        Route::get('/meetup/overview', function ($country) {
            return redirect("/{$country}/meetups");
        });
        // Redirect old meetup world URL to new map page
        Route::get('/meetup/world', function ($country) {
            return redirect("/{$country}/map");
        });
        // Redirect old meetup events URL to new meetups page
        Route::get('/meetup/meetup-events', function ($country) {
            return redirect("/{$country}/meetups");
        });
    // Old event landing page route (deprecated)
    Route::livewire('/meetup/meetup-events/l/{event}', 'meetups.landingpage-event')
        ->name('meetups.landingpage-event-old')
        ->where('event', '[0-9]+');

    /* Meetup related routes */
    Route::livewire('/meetups', 'meetups.index')->name('meetups.index');
    Route::livewire('/all-meetups', 'meetups.index')->name('meetups.index-all');
    Route::livewire('/map', 'meetups.map')->name('meetups.map');
    Route::livewire('/map-world', 'meetups.map')->name('meetups.map-world');
    Route::livewire('/meetup/{meetup:slug}', 'meetups.landingpage')->name('meetups.landingpage');
    Route::livewire('/meetup/{meetup:slug}/event/{event}',
        'meetups.landingpage-event')
        ->name('meetups.landingpage-event')
        ->where('event', '[0-9]+');

    /* Course related routes */
    Route::livewire('/courses', 'courses.index')->name('courses.index');
    Route::livewire('/course/{course}', 'courses.landingpage')->name('courses.landingpage');
    Route::livewire('/course/{course}/event/{event}', 'courses.landingpage-event')->name('courses.landingpage-event');

    /* Lecturer related routes */
    Route::livewire('/lecturers', 'lecturers.index')->name('lecturers.index');

    /* City and venue related routes */
    Route::livewire('/cities', 'cities.index')->name('cities.index');
    Route::livewire('/venues', 'venues.index')->name('venues.index');

    /* Self Hosted Services public routes */
    Route::livewire('/services', 'services.index')->name('services.index');
    Route::livewire('/service/{service:slug}', 'services.landingpage')->name('services.landingpage');
    });

// Authenticated user routes with country prefix
Route::middleware(['auth'])
    ->prefix('/{country:code}')
    ->group(function () {
        // Meetup creation and editing routes
        Route::livewire('/meetup-create', 'meetups.create')->name('meetups.create');
        Route::livewire('/meetup-edit/{meetup}', 'meetups.edit')->name('meetups.edit');
        Route::livewire('/meetup/{meetup}/events/create', 'meetups.create-edit-events')->name('meetups.events.create');
        Route::livewire('/meetup/{meetup}/events/{event}/edit', 'meetups.create-edit-events')->name('meetups.events.edit');

        // Course creation and editing routes
        Route::livewire('/course-create', 'courses.create')->name('courses.create');
        Route::livewire('/course-edit/{course}', 'courses.edit')->name('courses.edit');
        Route::livewire('/course/{course}/events/create', 'courses.create-edit-events')->name('courses.events.create');
        Route::livewire('/course/{course}/events/{event}/edit', 'courses.create-edit-events')->name('courses.events.edit');

        // Lecturer creation and editing routes
        Route::livewire('/lecturer-create', 'lecturers.create')->name('lecturers.create');
        Route::livewire('/lecturer-edit/{lecturer}', 'lecturers.edit')->name('lecturers.edit');

        // City creation and editing routes
        Route::livewire('/city-create', 'cities.create')->name('cities.create');
        Route::livewire('/city-edit/{city}', 'cities.edit')->name('cities.edit');

        // Venue creation and editing routes
        Route::livewire('/venue-create', 'venues.create')->name('venues.create');
        Route::livewire('/venue-edit/{venue}', 'venues.edit')->name('venues.edit');

        // Self Hosted Services protected routes (authenticated users only)
        Route::livewire('/service-create', 'services.create')->name('services.create');
        Route::livewire('/service-edit/{service}', 'services.edit')->name('services.edit');

        // Settings redirects and routes
        Route::redirect('settings', 'settings/profile');

        Route::livewire('/settings/profile', 'settings.profile')->name('settings.profile');
        Route::livewire('/settings/password', 'settings.password')->name('settings.password');
        Route::livewire('/settings/appearance', 'settings.appearance')->name('settings.appearance');
    });

// Commented out feed routes (RSS/Atom feeds)
// Route::feeds();

// Fallback route for handling 404 errors with rate limiting
Route::fallback(fn () => abort(404))
    ->middleware(Sample::rate(0.5));

// Include authentication routes from auth.php file
require __DIR__.'/auth.php';
