<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meetup;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

#[Group(name: 'Meetups', weight: 3)]
class MeetupController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function ical()
    {
        abort(404);
    }

    /**
     * Eigene Meetups auflisten
     *
     * Liefert die Meetups des angemeldeten Nutzers (id, name, inklusive Stadt/Land und Profilbild),
     * alphabetisch sortiert. Erfordert eine authentifizierte Sitzung (sonst 401).
     */
    #[QueryParameter(name: 'search', description: 'Teilstring-Suche im Meetup- oder Stadtnamen.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Lädt gezielt die angegebenen Meetup-IDs.', required: false, type: 'array')]
    #[Response(status: 401, description: 'Nicht authentifiziert.')]
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $myMeetupIds = $user->meetups->pluck('id');

        return Meetup::query()
            ->select('id', 'name', 'city_id', 'slug')
            ->with([
                'city.country',
            ])
            ->whereIn('id', $myMeetupIds->toArray())
            ->orderBy('name')
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$request->search}%")
                    ->orWhereHas('city',
                        fn (Builder $query) => $query->where('cities.name', 'ilike', "%{$request->search}%")),
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id', $request->input('selected', [])),
                fn (Builder $query) => $query->limit(10),
            )
            ->get()
            ->map(function (Meetup $meetup) {
                $meetup->profile_image = $meetup->getFirstMediaUrl('logo', 'thumb');

                return $meetup;
            });
    }

    #[ExcludeRouteFromDocs]
    public function store(Request $request)
    {
        //
    }

    #[ExcludeRouteFromDocs]
    public function show(Meetup $meetup)
    {
        //
    }

    #[ExcludeRouteFromDocs]
    public function update(Request $request, Meetup $meetup)
    {
        //
    }

    #[ExcludeRouteFromDocs]
    public function destroy(Meetup $meetup)
    {
        //
    }
}
