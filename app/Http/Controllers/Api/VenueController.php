<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\Venue;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

#[Group(name: 'Stammdaten', weight: 5)]
class VenueController extends Controller
{
    /**
     * Veranstaltungsorte auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id, name und die zugehörige Stadt/Land, alphabetisch sortiert.
     * Ohne 'selected' wird die Liste auf 10 Einträge begrenzt. Jeder Ort enthält zusätzlich
     * 'flag' (SVG-URL der Landesflagge) und 'description' (Stadt + Straße).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen des Veranstaltungsortes.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen Veranstaltungsort-IDs (umgeht die Begrenzung auf 10 Einträge).', required: false, type: 'array')]
    public function index(Request $request)
    {
        return Venue::query()
            ->with(['city:id,name,country_id', 'city.country:id,name,code'])
            ->select('id', 'name', 'city_id')
            ->orderBy('name')
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->where('name', 'ilike', "%{$request->search}%")
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id',
                    $request->input('selected', [])),
                fn (Builder $query) => $query->limit(10)
            )
            ->get()
            ->map(function (Venue $venue) {
                $venue->flag = asset('vendor/blade-country-flags/4x3-'.$venue->city->country->code.'.svg');
                $venue->description = $venue->city->name.', '.$venue->street;

                return $venue;
            });
    }

    /**
     * Store a newly created resource in storage.
     */
    #[ExcludeRouteFromDocs]
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    #[ExcludeRouteFromDocs]
    public function show(Lecturer $lecturer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    #[ExcludeRouteFromDocs]
    public function update(Request $request, Lecturer $lecturer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    #[ExcludeRouteFromDocs]
    public function destroy(Lecturer $lecturer)
    {
        //
    }
}
