<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lecturer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Course::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->when($request->has('user_id'),
                fn (Builder $query) => $query->where('created_by', $request->user_id))
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
            ->map(function (Course $course) {
                $course->image = $course->getFirstMediaUrl('logo',
                    'thumb');

                return $course;
            });
    }

    /**
     * Store a newly created resource in storage.
     *
     * Allows an authenticated lecturer to create a course programmatically
     * (e.g. to sync courses from an external system). Validation mirrors the
     * Livewire course create form; `created_by` is set by the model's creating hook.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()->is_lecturer, Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lecturer_id' => ['required', 'exists:lecturers,id'],
            'description' => ['nullable', 'string'],
        ]);

        $course = Course::create($validated);

        return response()->json($course->fresh(), Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * Authorized for the course owner (or a super-admin).
     */
    public function update(Request $request, Course $course): JsonResponse
    {
        abort_unless(
            (int) $course->created_by === $request->user()->id || $request->user()->hasRole('super-admin'),
            Response::HTTP_FORBIDDEN
        );

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'lecturer_id' => ['sometimes', 'required', 'exists:lecturers,id'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $course->update($validated);

        return response()->json($course->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        //
    }
}
