<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Community', weight: 7)]
class BtcMapCommunityController extends Controller
{
    /**
     * Einundzwanzig-Communities für BTC Map
     *
     * Liefert die Einundzwanzig-Communities im BTC-Map-Format (GeoJSON-Tags).
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(
            Meetup::query()
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
    }
}
