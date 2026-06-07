<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourseEventController extends Controller
{
    /**
     * Display a listing of the course events created by the authenticated user.
     *
     * Useful for an external sync client to detect which events already exist
     * (idempotent syncing). Optionally filtered by course_id.
     *
     * @return Collection<int, CourseEvent>
     */
    public function index(Request $request): Collection
    {
        return CourseEvent::query()
            ->with(['course:id,name', 'venue:id,name'])
            ->where('created_by', $request->user()->id)
            ->when(
                $request->filled('course_id'),
                fn (Builder $query) => $query->where('course_id', $request->integer('course_id'))
            )
            ->orderByDesc('from')
            ->get();
    }

    /**
     * Store a newly created course event in storage.
     *
     * Allows an authenticated lecturer to create a dated course event
     * programmatically. Validation mirrors the Livewire course event form;
     * `created_by` is set by the model's creating hook.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()->is_lecturer, Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'link' => ['required', 'url', 'max:255'],
        ]);

        $courseEvent = CourseEvent::create($validated);

        return response()->json($courseEvent->fresh(), Response::HTTP_CREATED);
    }

    /**
     * Update the specified course event in storage.
     *
     * Authorized for the course event owner (or a super-admin).
     */
    public function update(Request $request, CourseEvent $courseEvent): JsonResponse
    {
        abort_unless(
            (int) $courseEvent->created_by === $request->user()->id || $request->user()->hasRole('super-admin'),
            Response::HTTP_FORBIDDEN
        );

        $validated = $request->validate([
            'course_id' => ['sometimes', 'required', 'integer', 'exists:courses,id'],
            'venue_id' => ['sometimes', 'required', 'integer', 'exists:venues,id'],
            'from' => ['sometimes', 'required', 'date'],
            'to' => ['sometimes', 'required', 'date', 'after_or_equal:from'],
            'link' => ['sometimes', 'required', 'url', 'max:255'],
        ]);

        $courseEvent->update($validated);

        return response()->json($courseEvent->fresh());
    }
}
