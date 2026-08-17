<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCourseEventRequest;
use App\Http\Requests\Api\UpdateCourseEventRequest;
use App\Http\Resources\CourseEventResource;
use App\Models\CourseEvent;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'Course Events', weight: 2)]
class CourseEventController extends Controller
{
    /**
     * List own course events
     *
     * Returns all course events created by the authenticated user (including the associated
     * course and city), descending by start date. Ideal for idempotent
     * synchronization by external clients.
     */
    #[QueryParameter(name: 'course_id', description: 'Filters the course events down to a specific course.', required: false, type: 'integer')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $courseEvents = CourseEvent::query()
            ->with(['course:id,name', 'city:id,name'])
            ->where('created_by', $request->user()->id)
            ->when(
                $request->filled('course_id'),
                fn (Builder $query) => $query->where('course_id', $request->integer('course_id'))
            )
            ->orderByDesc('from')
            ->get();

        return CourseEventResource::collection($courseEvents);
    }

    /**
     * Create a course event
     *
     * Allows an authenticated lecturer to create a dated course event programmatically.
     */
    #[ResponseAttribute(status: 403, description: 'Only lecturers (is_lecturer) may create course events.')]
    public function store(StoreCourseEventRequest $request): JsonResponse
    {
        $courseEvent = CourseEvent::create($request->validated());

        return CourseEventResource::make($courseEvent->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a course event
     *
     * Updates a course event; only for the creator or a super admin.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator of the course event or a super admin may change it.')]
    public function update(UpdateCourseEventRequest $request, CourseEvent $courseEvent): CourseEventResource
    {
        $courseEvent->update($request->validated());

        return CourseEventResource::make($courseEvent->fresh());
    }
}
