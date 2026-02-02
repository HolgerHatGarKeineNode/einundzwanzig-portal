<?php

use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\HighscoreController;
use App\Http\Controllers\Api\LecturerController;
use App\Http\Controllers\Api\MeetupController;
use App\Http\Controllers\Api\VenueController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware([])
    ->as('api.')
    ->group(function () {
        Route::resource('countries', CountryController::class);
        Route::get('meetup/ical', [MeetupController::class, 'ical'])->name('api.meetup.ical');
        Route::resource('meetup', MeetupController::class);
        Route::resource('lecturers', LecturerController::class);
        Route::resource('courses', CourseController::class);
        Route::resource('cities', CityController::class);
        Route::resource('venues', VenueController::class);
        Route::get('highscores', [HighscoreController::class, 'index'])->name('highscores.index');
        Route::post('highscores', [HighscoreController::class, 'store'])->name('highscores.store');
        Route::get('nostrplebs', function () {
            return User::query()
                ->select([
                    'email',
                    'public_key',
                    'lightning_address',
                    'lnurl',
                    'node_id',
                    'paynym',
                    'lnbits',
                    'nostr',
                    'id',
                ])
                ->whereNotNull('nostr')
                ->where('nostr', 'like', 'npub1%')
                ->orderByDesc('id')
                ->get()
                ->unique('nostr')
                ->pluck('nostr');
        });
        Route::get('bindles', function () {
            return \App\Models\LibraryItem::query()
                ->where('type', 'bindle')
                ->with([
                    'media',
                ])
                ->orderByDesc('id')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'link' => strtok($item->value, '?'),
                    'image' => $item->getFirstMediaUrl('main'),
                ]);
        });
        Route::get('meetups', function (Request $request) {
            return \App\Models\Meetup::query()
                ->where('visible_on_map', true)
                ->with([
                    'meetupEvents',
                    'city.country',
                    'media',
                ])
                ->get()
                ->map(fn ($meetup) => [
                    'name' => $meetup->name,
                    'portalLink' => url()->route(
                        'meetups.landingpage',
                        ['country' => $meetup->city->country, 'meetup' => $meetup],
                    ),
                    'url' => $meetup->telegram_link ?? $meetup->webpage,
                    'top' => $meetup->github_data['top'] ?? null,
                    'left' => $meetup->github_data['left'] ?? null,
                    'country' => str($meetup->city->country->code)->upper(),
                    'state' => $meetup->github_data['state'] ?? null,
                    'city' => $meetup->city->name,
                    'longitude' => (float) $meetup->city->longitude,
                    'latitude' => (float) $meetup->city->latitude,
                    'twitter_username' => $meetup->twitter_username,
                    'website' => $meetup->webpage,
                    'simplex' => $meetup->simplex,
                    'signal' => $meetup->signal,
                    'nostr' => $meetup->nostr,
                    'next_event' => $meetup->nextEvent,
                    'intro' => $request->has('withIntro') ? $meetup->intro : null,
                    'logo' => $request->has('withLogos') ? $meetup->getFirstMediaUrl('logo') : null,
                ]);
        });
        Route::get('meetup-events/{date?}', function ($date = null) {
            if ($date) {
                $date = \Carbon\Carbon::parse($date);
            }
            $events = \App\Models\MeetupEvent::query()
                ->with([
                    'meetup.city.country',
                    'meetup.media',
                ])
                ->when(
                    $date,
                    fn ($query) => $query
                        ->where('start', '>=', $date)
                        ->where('start', '<=', $date->copy()->endOfMonth()),
                )
                ->get();

            return $events->map(fn ($event) => [
                'start' => $event->start->format('Y-m-d H:i'),
                'location' => $event->location,
                'description' => $event->description,
                'link' => $event->link,
                'meetup.name' => $event->meetup->name,
                'meetup.portalLink' => url()->route(
                    'meetups.landingpage',
                    [
                        'country' => $event->meetup->city->country,
                        'meetup' => $event->meetup,
                    ],
                ),
                'meetup.url' => $event->meetup->telegram_link ?? $event->meetup->webpage,
                'meetup.country' => str($event->meetup->city->country->code)->upper(),
                'meetup.city' => $event->meetup->city->name,
                'meetup.longitude' => (float) $event->meetup->city->longitude,
                'meetup.latitude' => (float) $event->meetup->city->latitude,
                'meetup.twitter_username' => $event->meetup->twitter_username,
                'meetup.website' => $event->meetup->webpage,
                'meetup.simplex' => $event->meetup->simplex,
                'meetup.signal' => $event->meetup->signal,
                'meetup.nostr' => $event->meetup->nostr,
                'meetup.logo' => $event->meetup->getFirstMediaUrl('logo'),
            ],
            );
        });
        Route::get('btc-map-communities', function () {
            return response()->json(
                \App\Models\Meetup::query()
                    ->with([
                        'media',
                        'city.country',
                    ])
                    ->where('community', '=', 'einundzwanzig')
                    ->when(
                        app()->environment('production'),
                        fn ($query) => $query->whereHas(
                            'city',
                            fn ($query) => $query
                                ->whereNotNull('cities.simplified_geojson')
                                ->whereNotNull('cities.population')
                                ->whereNotNull('cities.population_date'),
                        ),
                    )
                    ->get()
                    ->map(fn ($meetup) => [
                        'id' => $meetup->slug,
                        'tags' => [
                            'type' => 'community',
                            'name' => $meetup->name,
                            'continent' => 'europe',
                            'icon:square' => $meetup->logoSquare,
                            // 'contact:email'          => null,
                            'contact:twitter' => $meetup->twitter_username ? 'https://twitter.com/'.$meetup->twitter_username : null,
                            'contact:website' => $meetup->webpage,
                            'contact:telegram' => $meetup->telegram_link,
                            'contact:nostr' => $meetup->nostr,
                            // 'tips:lightning_address' => null,
                            'organization' => 'einundzwanzig',
                            'language' => $meetup->city->country->language_codes[0] ?? 'de',
                            'geo_json' => $meetup->city->simplified_geojson,
                            'population' => $meetup->city->population,
                            'population:date' => $meetup->city->population_date,
                        ],
                    ])
                    ->toArray(),
                200,
                ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'],
                JSON_UNESCAPED_SLASHES,
            );
        });
    });

Route::get('/lnurl-auth-callback', [\App\Http\Controllers\LnurlAuthController::class, 'callback'])
    ->name('auth.ln.callback');

Route::post('/check-auth-error', [\App\Http\Controllers\LnurlAuthController::class, 'checkError'])
    ->name('auth.check-error');
