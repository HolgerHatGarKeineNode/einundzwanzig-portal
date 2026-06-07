<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

#[Group(name: 'Referenten', weight: 4)]
class LecturerController extends Controller
{
    /**
     * Referenten auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id und name, alphabetisch sortiert. Ohne den Parameter 'selected' wird die Liste auf 10 Einträge begrenzt. Jeder Referent enthält zusätzlich ein 'image' (Avatar-Thumbnail-URL).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Namen.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen IDs.', required: false, type: 'array')]
    public function index(Request $request)
    {
        return Lecturer::query()
            ->select('id', 'name')
            ->orderBy('name')
//                       ->when($request->has('user_id'),
//                           fn(Builder $query) => $query->where('created_by', $request->user_id))
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
            ->map(function (Lecturer $lecturer) {
                $lecturer->image = $lecturer->getFirstMediaUrl('avatar',
                    'thumb');

                return $lecturer;
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
