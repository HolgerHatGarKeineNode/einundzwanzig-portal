<?php

use App\Http\Controllers\Api\BtcMapCommunityController;
use App\Http\Controllers\Api\ChangeController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseEventController;
use App\Http\Controllers\Api\LecturerController;
use App\Http\Controllers\Api\MeetupController;
use App\Http\Controllers\Api\MeetupEventController;
use App\Http\Controllers\Api\MeetupLeaderController;
use App\Http\Controllers\Api\MeetupMapController;
use App\Http\Controllers\Api\MobileMeetupListController;
use App\Http\Controllers\Api\NostrPlebController;
use App\Http\Controllers\Api\PublicMeetupLeaderController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VereinGatedMeetupController;
use App\Http\Controllers\Api\WebhookSubscriptionController;
use App\Http\Controllers\LnurlAuthController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\VereinGateToken;
use Illuminate\Support\Facades\Route;

Route::middleware([SetApiLocale::class, 'throttle:60,1'])
    ->as('api.')
    ->group(function () {
        Route::resource('countries', CountryController::class)->only(['index']);
        Route::get('meetup/ical', [MeetupController::class, 'ical'])->name('api.meetup.ical');
        Route::resource('meetup', MeetupController::class)->only(['index']);
        Route::resource('lecturers', LecturerController::class)->only(['index', 'show']);
        Route::resource('courses', CourseController::class)
            ->only(['index', 'show']);
        Route::resource('cities', CityController::class)->only(['index']);
        Route::get('nostrplebs', NostrPlebController::class);
        Route::get('meetups', MeetupMapController::class);
        // Schlanke, schnelle Meetup-Liste eigens für die mobile App (getrennt von
        // /api/meetups, damit andere Konsumenten der Karte unberührt bleiben).
        Route::get('mobile/meetups', MobileMeetupListController::class);
        // Leader-npubs je Meetup fuer die Badge-Pruefung externer Clients.
        // Bewusst NICHT in der API-Referenz (ExcludeRouteFromDocs) — erreichbar,
        // aber nicht beworben, solange die Form sich noch setzen kann.
        Route::get('meetup-leaders', PublicMeetupLeaderController::class);
        // Zwei Routen statt `meetup-events/{date?}`, damit der nackte Pfad in der
        // API-Referenz ueberhaupt auftaucht: Scramble kollabiert einen optionalen
        // Pfad-Parameter und dokumentiert nur die Variante MIT ihm (Issue #57).
        // Gleiches Verhalten wie vorher — index() delegiert an __invoke().
        Route::get('meetup-events', [MeetupEventController::class, 'index']);
        Route::get('meetup-events/{date}', MeetupEventController::class);
        Route::get('btc-map-communities', BtcMapCommunityController::class);
        // Der Resync-Weg fuer Konsumenten (Issue #29): alle Aenderungen ab einem
        // Cursor, inklusive der Loeschungen, die sonst nirgends sichtbar sind.
        Route::get('changes', [ChangeController::class, 'index'])->name('changes.index');
    });

/*
 * Authenticated write endpoints (Sanctum token auth).
 * Lets a lecturer create/update their own courses and course events
 * programmatically, e.g. to sync events from an external system.
 *
 * `throttle` ist hier kein Schmuck, sondern die einzige Volumenbremse dieser Gruppe:
 * `bootstrap/app.php` ruft `throttleApi()` nicht auf, also erbt sie von aussen nichts.
 * Bis Issue #30 war der offene Lesepfad gedrosselt und der authentifizierte
 * Schreibpfad ungedrosselt — genau andersherum als gedacht. 60/min laesst einen
 * ehrlichen Massenimport (mehrere hundert Staedte) in Minuten durchlaufen und
 * begrenzt trotzdem, was ein einzelnes Token in einer Stunde anrichten kann.
 */
Route::middleware([SetApiLocale::class, 'auth:sanctum', 'throttle:60,1'])
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

        // Leader-Delegation: bestehende Leader setzen weitere Leader per npub
        // ein bzw. entziehen sie (meetup_user.is_leader). Siehe MeetupPolicy.
        Route::get('meetup/{meetup}/leaders', [MeetupLeaderController::class, 'index'])->name('meetup.leaders.index');
        Route::post('meetup/{meetup}/leaders', [MeetupLeaderController::class, 'store'])->name('meetup.leaders.store');
        Route::delete('meetup/{meetup}/leaders/{user}', [MeetupLeaderController::class, 'destroy'])->name('meetup.leaders.destroy');

        Route::post('meetup-events', [MeetupEventController::class, 'store'])->name('meetup-events.store');
        Route::patch('meetup-events/{meetupEvent}', [MeetupEventController::class, 'update'])->name('meetup-events.update');
        Route::get('my-meetup-events', [MeetupEventController::class, 'mine'])->name('meetup-events.mine');
        Route::get('my-meetup-events/{meetupEvent}', [MeetupEventController::class, 'mineShow'])->name('meetup-events.mine.show');
        Route::get('meetup-events/{meetupEvent}/rsvp', [MeetupEventController::class, 'rsvpStatus'])->name('meetup-events.rsvp.show');
        Route::post('meetup-events/{meetupEvent}/rsvp', [MeetupEventController::class, 'rsvp'])->name('meetup-events.rsvp');

        // Self-service outbound webhook subscriptions for meetup/meetup-event changes
        // (Issue #36). New subscriptions start pending behind
        // einundzwanzig.webhooks.require_approval — see WebhookSubscriptionController.
        Route::get('webhook-subscriptions', [WebhookSubscriptionController::class, 'index'])->name('webhook-subscriptions.index');
        Route::post('webhook-subscriptions', [WebhookSubscriptionController::class, 'store'])->name('webhook-subscriptions.store');
        Route::patch('webhook-subscriptions/{webhookSubscription}', [WebhookSubscriptionController::class, 'update'])->name('webhook-subscriptions.update');
        Route::delete('webhook-subscriptions/{webhookSubscription}', [WebhookSubscriptionController::class, 'destroy'])->name('webhook-subscriptions.destroy');
    });

// Vereinsmitglied-gegatete Meetups für den Nostr-Client (Server-zu-Server,
// Bearer-Token statt Sanctum-Session). Nur Meetups mit echtem Vereinsbezug.
Route::get('/verein/gated-meetups', VereinGatedMeetupController::class)
    ->middleware([SetApiLocale::class, VereinGateToken::class, 'throttle:60,1'])
    ->name('api.verein.gated-meetups');

Route::get('/lnurl-auth-callback', [LnurlAuthController::class, 'callback'])
    ->name('auth.ln.callback');

// NIP-55 signer callback (e.g. Amber) for the mobile auth flow.
Route::get('/nostr-login-callback', [MobileAuthController::class, 'nostrCallback'])
    ->middleware('throttle:30,1')
    ->name('auth.nostr.callback');

// Replay-protected Nostr login for the in-page welshman signer flow (new
// app versions). challenge issues a single-use k1; nostr/token trades a
// kind-22242 event signed over that k1 for a Sanctum token and consumes it
// once. Separate URLs from the legacy /mobile/token below so released app
// builds keep working unchanged.
Route::get('/mobile/nostr/challenge', [MobileAuthController::class, 'nostrChallenge'])
    ->middleware([SetApiLocale::class, 'throttle:30,1'])
    ->name('auth.mobile.nostr.challenge');

Route::post('/mobile/nostr/token', [MobileAuthController::class, 'nostrToken'])
    ->middleware([SetApiLocale::class, 'throttle:30,1'])
    ->name('auth.mobile.nostr.token');

// Token exchange for the mobile app: trades a NIP-55-signed login event
// for a Sanctum personal access token (used when the signer callback
// opens the app directly via a verified App Link).
Route::post('/mobile/token', [MobileAuthController::class, 'token'])
    ->middleware([SetApiLocale::class, 'throttle:30,1'])
    ->name('auth.mobile.token');

// Logout for the mobile app: revokes the personal access token that
// authenticated this request, so a local "disconnect" in the app also
// invalidates the token server-side.
Route::delete('/mobile/token', [MobileAuthController::class, 'revoke'])
    ->middleware([SetApiLocale::class, 'auth:sanctum', 'throttle:30,1'])
    ->name('auth.mobile.token.revoke');

Route::post('/check-auth-error', [LnurlAuthController::class, 'checkError'])
    ->name('auth.check-error');
