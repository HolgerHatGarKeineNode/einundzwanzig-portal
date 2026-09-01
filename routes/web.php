<?php

use App\Http\Controllers\DownloadMeetupCalendar;
use App\Http\Controllers\ImageController;
use App\Livewire\Helper\FollowTheRabbit;
use App\Support\RegionRoutes;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Http\Middleware\Sample;

// Redirect root URL to 'welcome' page
Route::redirect('/', 'welcome');

// Error page route that aborts with given HTTP status code (digits only,
// constrained to valid 4xx/5xx range to avoid TypeErrors from bot scans).
Route::get('error/{code}', function (string $code) {
    $code = (int) $code;
    abort($code >= 400 && $code <= 599 ? $code : 404);
})->where('code', '[0-9]{3}');

// Route for rabbit following helper page - Updated for Livewire v4
Route::livewire('/kaninchenbau', FollowTheRabbit::class)
    ->name('kaninchenbau');

// Generic image handler route that serves images from storage
Route::get('/img/{path}', ImageController::class)
    ->where('path', '[A-Za-z0-9._\-/]+')
    ->name('img');

// Public image handler route for serving public images
Route::get('/img-public/{path}', ImageController::class)
    ->where('path', '[A-Za-z0-9._\-/]+')
    ->name('imgPublic');

// Welcome page route using Volt component
Route::livewire('/welcome', 'welcome')->name('welcome');

// Public guide explaining the MCP/AI connector and the claude.ai setup
Route::livewire('/ki-assistent', 'ki-assistent')->name('ki-assistent');

/*
 * Consumer documentation for the realtime change feed (WebSocket channels plus
 * /api/changes). Sits next to Scramble's /docs/api, which links here from its
 * description — the channels are not Laravel routes, so Scramble cannot generate them.
 */
Route::livewire('/docs/websockets', 'docs.websockets')->name('docs.websockets');

// Stream calendar route to download meetup calendar as ICS file
Route::get('stream-calendar', DownloadMeetupCalendar::class)
    ->name('ics')
    ->middleware(['throttle:calendar', Sample::never()]);

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
        Route::livewire('/tags/moderation', 'tags.moderation')->name('tags.moderation');
    });

// Country-specific routes group with mandatory country code parameter
Route::middleware([])
    ->prefix('/{country:code}')
    ->group(function () {
        /* OLD URLS - redirects for legacy URLs */
        // Redirect old meetup calendar route to new one
        Route::get('meetup/stream-calendar', DownloadMeetupCalendar::class)
            ->name('ics-meetup')
            ->middleware(['throttle:calendar', Sample::never()]);
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
            ->where('event', '[0-9]+')
            ->middleware(Sample::never());

        /* Course related routes */
        Route::livewire('/courses', 'courses.index')->name('courses.index');
        Route::livewire('/course/{course}', 'courses.landingpage')->name('courses.landingpage');
        Route::livewire('/course/{course}/event/{event}', 'courses.landingpage-event')->name('courses.landingpage-event');

        /* Lecturer related routes */
        Route::livewire('/lecturers', 'lecturers.index')->name('lecturers.index');

        /* City related routes */
        Route::livewire('/cities', 'cities.index')->name('cities.index');

        /*
         * /venues was a public, indexed URL for years. The page is gone — a venue is now
         * just a line on the event that happens there — but a 404 would throw away that
         * traffic and every inbound link. The course list is where those events live now.
         *
         * Deliberately unnamed: nothing in the app should link here any more, new links
         * belong on courses.index directly.
         */
        Route::redirect('/venues', '/{country}/courses', 301);

        /* Self Hosted Services public routes */
        Route::livewire('/services', 'services.index')->name('services.index');
        Route::livewire('/service/{service:slug}', 'services.landingpage')->name('services.landingpage');

        /*
         * Region-gefilterte Listen: /us/in/meetups, /de/by/map, /gb/eng/cities, /at/9/map …
         *
         * ## Warum nicht mehr `[a-z]{2}`
         *
         * Das Muster stand auf ZWEI Kleinbuchstaben. ISO 3166-2 gibt das nicht her: der
         * Suffix ist ein bis drei alphanumerische Zeichen. Gemessen an
         * `database/data/regions.csv` fuer die unterstuetzten Laender
         * ({@see \App\Console\Commands\ImportRegions}) sind das:
         * AT eine Ziffer (`1`…`9`), GB/BE/LV drei Buchstaben (`eng`, `bru`, `011`),
         * AU und CO gemischt zwei und drei. Auf Produktion waren dadurch u. a. die 13
         * franzoesischen Regionen (`ara`, `bfc`, …) ueber KEINE URL erreichbar — die
         * Zeilen standen in der Datenbank und niemand kam an sie heran.
         *
         * `{1,3}` statt `{2,3}`: ohne die Eins faellt Oesterreich komplett heraus.
         * Grossbuchstaben bleiben draussen, weil `ImportRegions` den Code
         * kleingeschrieben aus der CSV uebernimmt — ein `/de/BY/meetups` waere eine
         * zweite URL fuer denselben Inhalt.
         *
         * ## Warum das keine andere Route kapert (geprueft, nicht vermutet)
         *
         * Diese drei Muster greifen nur bei GENAU drei Segmenten, deren drittes das feste
         * Wort `meetups`, `map` oder `cities` ist. Jede andere Route dieser Gruppe mit
         * einem variablen zweiten Segment hat entweder ein anderes drittes Wort
         * (`/meetup/{meetup}/event/{event}`, `/course/{course}/event/{event}`) oder gar
         * kein drittes. Und jede Route mit festem zweitem Segment traegt dort ein Wort
         * mit mehr als drei Zeichen (`meetup`, `course`, `service`, `tags`,
         * `all-meetups`), faellt also schon am Muster durch. Die Registrierung am Ende
         * der Gruppe ist der zweite Guertel; `tests/Feature/RegionRouteSegmentTest.php`
         * misst beides.
         *
         * Eigene Routennamen statt zusätzlicher Parameter an den bestehenden: so ändert
         * sich kein einziger vorhandener route()-Aufruf, und route_with_country() bleibt
         * regionsfrei.
         */
        Route::livewire('/{region}/meetups', 'meetups.index')
            ->name('meetups.index-region')
            ->where('region', RegionRoutes::SEGMENT_PATTERN);
        Route::livewire('/{region}/map', 'meetups.map')
            ->name('meetups.map-region')
            ->where('region', RegionRoutes::SEGMENT_PATTERN);
        Route::livewire('/{region}/cities', 'cities.index')
            ->name('cities.index-region')
            ->where('region', RegionRoutes::SEGMENT_PATTERN);
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

        // Self Hosted Services protected routes (authenticated users only)
        Route::livewire('/service-create', 'services.create')->name('services.create');
        Route::livewire('/service-edit/{service}', 'services.edit')->name('services.edit');

        // Settings redirects and routes
        Route::redirect('settings', 'settings/profile');

        Route::livewire('/settings/profile', 'settings.profile')->name('settings.profile');
        Route::livewire('/settings/password', 'settings.password')->name('settings.password');
        Route::livewire('/settings/appearance', 'settings.appearance')->name('settings.appearance');
        Route::livewire('/settings/api-tokens', 'settings.api-tokens')->name('settings.api-tokens');
        Route::livewire('/settings/webhooks', 'settings.webhooks')->name('settings.webhooks');
        Route::livewire('/settings/link-identity', 'settings.link-identity')->name('settings.link-identity');

        // Board-only admin view to approve/revoke pending webhook subscriptions
        // (Issue #40). 403 for non-board users is enforced in the component's
        // mount() via BoardGate, not by route middleware.
        Route::livewire('/admin/webhooks', 'admin.webhooks')->name('admin.webhooks');
    });

// Commented out feed routes (RSS/Atom feeds)
// Route::feeds();

// Fallback route for handling 404 errors with rate limiting
Route::fallback(fn () => abort(404))
    ->middleware(Sample::rate(0.5));

// Include authentication routes from auth.php file
require __DIR__.'/auth.php';
