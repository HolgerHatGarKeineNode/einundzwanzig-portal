<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Lecturer;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

#[Group(name: 'Stammdaten', weight: 5)]
class CityController extends Controller
{
    /**
     * Städte auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id, name und das zugehörige Land, alphabetisch sortiert. Ohne 'selected' wird die Liste auf 10 Einträge begrenzt.
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen der Stadt.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen IDs.', required: false, type: 'array')]
    public function index(Request $request)
    {
        return City::query()
            ->with(['country:id,name'])
            ->select('id', 'name', 'country_id')
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
            ->get();
    }

    #[ExcludeRouteFromDocs]
    public function store(Request $request)
    {
        //
    }

    #[ExcludeRouteFromDocs]
    public function show(Lecturer $lecturer)
    {
        //
    }

    #[ExcludeRouteFromDocs]
    public function update(Request $request, Lecturer $lecturer)
    {
        //
    }

    #[ExcludeRouteFromDocs]
    public function destroy(Lecturer $lecturer)
    {
        //
    }
}
