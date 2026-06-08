<?php

namespace App\Mcp\Tools\Lecturer;

use App\Http\Resources\LecturerResource;
use App\Models\Lecturer;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Listet alle vom authentifizierten Nutzer erstellten Referenten, alphabetisch sortiert.')]
class ListMyLecturersTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('viewAny', Lecturer::class)) {
            return Response::error('Nicht authentifiziert.');
        }

        $lecturers = Lecturer::query()
            ->where('created_by', $user->getAuthIdentifier())
            ->orderBy('name')
            ->get();

        return Response::json(LecturerResource::collection($lecturers)->resolve());
    }
}
