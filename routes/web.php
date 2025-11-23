<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', 'welcome');

Route::get('error/{code}', function ($code) {
    abort($code);
});

/*Route::get('/download-buecherverleih', function (Request $request) {
    $filename = $request->input('filename');
    // Get the file path from the public folder
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

Route::middleware([])
    ->get('/kaninchenbau', \App\Livewire\Helper\FollowTheRabbit::class)
    ->name('kaninchenbau');

Route::get('/img/{path}', \App\Http\Controllers\ImageController::class)
    ->where('path', '.*')
    ->name('img');

Route::get('/img-public/{path}', \App\Http\Controllers\ImageController::class)
    ->where('path', '.*')
    ->name('imgPublic');

Volt::route('welcome', 'welcome')->name('welcome');

Route::get('stream-calendar', \App\Http\Controllers\DownloadMeetupCalendar::class)
    ->name('ics');

Route::middleware(['auth'])
    ->get('dashboard', function () {
        return redirect('/de/dashboard'); // Zu /de weiterleiten
    });

Route::middleware([])
    ->prefix('/{country:code}')
    ->group(function () {
        /* OLD URLS */
        Route::get('meetup/stream-calendar', \App\Http\Controllers\DownloadMeetupCalendar::class)
            ->name('ics');
        Route::get('/meetup/overview', function ($country) {
            return redirect("/{$country}/meetups");
        });
        Route::get('/meetup/world', function ($country) {
            return redirect("/{$country}/map");
        });
        Route::get('/meetup/meetup-events', function ($country) {
            return redirect("/{$country}/meetups");
        });

        Volt::route('meetups', 'meetups.index')->name('meetups.index');
        Volt::route('map', 'meetups.map')->name('meetups.map');
        Volt::route('meetup/{meetup:slug}', 'meetups.landingpage')->name('meetups.landingpage');
        Volt::route('meetup/{meetup:slug}/event/{event}',
            'meetups.landingpage-event')->name('meetups.landingpage-event');

        Volt::route('courses', 'courses.index')->name('courses.index');
        Volt::route('course/{course}', 'courses.landingpage')->name('courses.landingpage');
        Volt::route('course/{course}/event/{event}', 'courses.landingpage-event')->name('courses.landingpage-event');

        Volt::route('lecturers', 'lecturers.index')->name('lecturers.index');

        Volt::route('cities', 'cities.index')->name('cities.index');
        Volt::route('venues', 'venues.index')->name('venues.index');
    });

Route::middleware(['auth'])
    ->prefix('/{country:code}')
    ->group(function () {
        Volt::route('dashboard', 'dashboard')->name('dashboard');
        Volt::route('meetup-create', 'meetups.create')->name('meetups.create');
        Volt::route('meetup-edit/{meetup}', 'meetups.edit')->name('meetups.edit');
        Volt::route('meetup/{meetup}/events/create', 'meetups.create-edit-events')->name('meetups.events.create');
        Volt::route('meetup/{meetup}/events/{event}/edit', 'meetups.create-edit-events')->name('meetups.events.edit');

        Volt::route('course-create', 'courses.create')->name('courses.create');
        Volt::route('course-edit/{course}', 'courses.edit')->name('courses.edit');
        Volt::route('course/{course}/events/create', 'courses.create-edit-events')->name('courses.events.create');
        Volt::route('course/{course}/events/{event}/edit', 'courses.create-edit-events')->name('courses.events.edit');

        Volt::route('lecturer-create', 'lecturers.create')->name('lecturers.create');
        Volt::route('lecturer-edit/{lecturer}', 'lecturers.edit')->name('lecturers.edit');

        Volt::route('city-create', 'cities.create')->name('cities.create');
        Volt::route('city-edit/{city}', 'cities.edit')->name('cities.edit');

        Volt::route('venue-create', 'venues.create')->name('venues.create');
        Volt::route('venue-edit/{venue}', 'venues.edit')->name('venues.edit');
    });

Route::middleware(['auth'])
    ->group(function () {
        Route::redirect('settings', 'settings/profile');

        Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
        Volt::route('settings/password', 'settings.password')->name('settings.password');
        Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    });

//Route::feeds();

require __DIR__.'/auth.php';
