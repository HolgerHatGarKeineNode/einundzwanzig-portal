<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

#[Group(name: 'Stammdaten', weight: 5)]
class CountryController extends Controller
{
    /**
     * Länder auflisten und durchsuchen
     *
     * Öffentlicher Endpunkt; liefert id, name und code (Ländercode), alphabetisch sortiert. Ohne 'selected' wird das Ergebnis auf 10 Einträge begrenzt. Jedes Land enthält zusätzlich eine 'flag' (SVG-URL).
     */
    #[QueryParameter(name: 'search', description: 'Suche in Name oder Code (Ländercode).', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen Codes oder IDs.', required: false, type: 'array')]
    public function index(Request $request)
    {
        return Country::query()
            ->select('id', 'name', 'code')
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
            ->map(function (Country $country) {
                $country->flag = asset('vendor/blade-flags/country-'.$country->code.'.svg');

                return $country;
            });
    }
}
