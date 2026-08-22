<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

#[Group(name: 'Master Data', weight: 5)]
class CountryController extends Controller
{
    /**
     * List and search countries
     *
     * Public endpoint; returns id, name and code (country code), sorted alphabetically. Without 'selected' the result is capped at 10 items. Every country additionally contains a 'flag' (SVG URL) and, where known, its OpenStreetMap reference (osm_type, osm_id, osm_url), Wikidata and Wikipedia links, and coordinates.
     */
    #[QueryParameter(name: 'search', description: 'Search in name or code (country code).', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Loads exactly the given codes or IDs.', required: false, type: 'array')]
    public function index(Request $request)
    {
        return CountryResource::collection(
            Country::query()
                // Ausdrueckliche Spaltenliste statt select('*'): was die API zusagt, steht
                // in der Resource, und was sie dafuer braucht, steht hier.
                ->select(
                    'id', 'name', 'code',
                    'osm_type', 'osm_id', 'osm_name',
                    'wikidata', 'wikipedia',
                    'latitude', 'longitude',
                )
                ->orderBy('name')
                ->when(
                    $request->search,
                    fn (Builder $query) => $query
                        ->whereLike('name', "%{$request->search}%")
                        ->orWhereLike('code', "%{$request->search}%"),
                )
                ->when(
                    $request->exists('selected'),
                    function (Builder $query) use ($request) {
                        $selected = $request->input('selected', []);

                        $query->whereIn('code', $selected)
                            ->orWhereIn('id', array_filter($selected, 'is_numeric'));
                    },
                    fn (Builder $query) => $query->limit(10),
                )
                ->get()
        )
            /*
             * ->resolve() statt der Resource-Collection selbst: die haengt die Antwort
             * sonst unter einen "data"-Schluessel, und GET /countries liefert seit jeher
             * ein nacktes Array. `$wrap = null` auf der Resource allein genuegt dafuer
             * nicht — es greift bei ::make(), nicht bei ::collection().
             */
            ->resolve();
    }
}
