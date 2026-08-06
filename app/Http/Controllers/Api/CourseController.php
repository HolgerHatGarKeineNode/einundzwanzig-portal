<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCourseRequest;
use App\Http\Requests\Api\UpdateCourseRequest;
use App\Http\Requests\Api\UploadMediaRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'Courses', weight: 1)]
class CourseController extends Controller
{
    use FiltersNumericIds;

    /**
     * List and search courses
     *
     * Public endpoint; returns id and name, sorted alphabetically. Without the
     * 'selected' parameter the result is capped at 10 items. Every course additionally
     * contains an 'image' (logo thumbnail URL). With 'withDetails' the limit is dropped and
     * every course additionally contains description, lecturer (id, name, subtitle, image) and
     * next_event (start of the next upcoming course event, or null).
     */
    #[QueryParameter(name: 'search', description: 'Substring search in the course name.', required: false, type: 'string')]
    #[QueryParameter(name: 'user_id', description: 'Filters the courses by their creator.', required: false, type: 'integer')]
    #[QueryParameter(name: 'selected', description: 'Loads exactly the given course IDs.', required: false, type: 'array')]
    #[QueryParameter(name: 'withDetails', description: 'Presence flag: also returns description, lecturer and next_event and lifts the 10-item limit.', required: false, type: 'string')]
    public function index(Request $request)
    {
        $withDetails = $request->exists('withDetails');

        return Course::query()
            ->select($withDetails ? ['id', 'name', 'description', 'lecturer_id'] : ['id', 'name'])
            ->with('media')
            ->orderBy('name')
            ->when($withDetails, fn (Builder $query) => $query
                ->with('lecturer.media')
                ->withMin(['courseEvents as next_event' => fn (Builder $events) => $events->where('from', '>=', now())], 'from'))
            ->when($request->has('user_id'),
                fn (Builder $query) => $query->where('created_by', $request->integer('user_id')))
            ->when(
                $request->search,
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$request->search}%")
            )
            ->when(
                $request->exists('selected'),
                fn (Builder $query) => $query->whereIn('id', $this->numericIds($request)),
                fn (Builder $query) => $withDetails ? $query : $query->limit(10)
            )
            ->get()
            ->map(function (Course $course) use ($withDetails) {
                $course->image = $course->getFirstMediaUrl('logo',
                    'thumb');

                if (! $withDetails) {
                    return $course;
                }

                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'image' => $course->image,
                    'description' => $course->description,
                    'next_event' => $course->next_event,
                    'lecturer' => $this->lecturerSummary($course->lecturer),
                ];
            });
    }

    /**
     * Lecturer summary for course responses (list and detail).
     *
     * @return array<string, mixed>|null
     */
    private function lecturerSummary(?Lecturer $lecturer): ?array
    {
        if ($lecturer === null) {
            return null;
        }

        return [
            'id' => $lecturer->id,
            'name' => $lecturer->name,
            'subtitle' => $lecturer->subtitle,
            'image' => $lecturer->getFirstMediaUrl('avatar', 'thumb'),
        ];
    }

    /**
     * Create a course
     *
     * Allows an authenticated lecturer to create a course programmatically.
     * The creator (created_by) is set automatically to the signed-in user.
     */
    #[ResponseAttribute(status: 403, description: 'Only lecturers (is_lecturer) may create courses.')]
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = Course::create($request->validated());

        return CourseResource::make($course->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a course
     *
     * Public endpoint; returns a course with description, logo, lecturer
     * and all upcoming course events (including venue and city),
     * sorted ascending by start.
     *
     * @return array<string, mixed>
     */
    public function show(Course $course): array
    {
        $course->load([
            'lecturer.media',
            'media',
            'courseEvents' => fn ($query) => $query
                ->where('from', '>=', now())
                ->orderBy('from')
                ->with('venue.city.country'),
        ]);

        return [
            'id' => $course->id,
            'name' => $course->name,
            'description' => $course->description,
            'image' => $course->getFirstMediaUrl('logo', 'preview'),
            'portalLink' => url()->route('courses.landingpage', [
                'country' => config('app.domain_country'),
                'course' => $course,
            ]),
            'lecturer' => $this->lecturerSummary($course->lecturer),
            'events' => $course->courseEvents->map(fn (CourseEvent $event) => [
                'id' => $event->id,
                'course_id' => $event->course_id,
                'venue_id' => $event->venue_id,
                'from' => $event->from,
                'to' => $event->to,
                'link' => $event->link,
                'venue' => [
                    'id' => $event->venue->id,
                    'name' => $event->venue->name,
                    'city' => [
                        'id' => $event->venue->city->id,
                        'name' => $event->venue->city->name,
                        'country_id' => $event->venue->city->country_id,
                        'country' => [
                            'id' => $event->venue->city->country->id,
                            'name' => $event->venue->city->country->name,
                            'code' => $event->venue->city->country->code,
                        ],
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * Update a course
     *
     * Updates a course; only for the creator or a super admin.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator of the course or a super admin may change it.')]
    public function update(UpdateCourseRequest $request, Course $course): CourseResource
    {
        $course->update($request->validated());

        return CourseResource::make($course->fresh());
    }

    /**
     * Upload a course logo
     *
     * Uploads a logo (multipart, field "file") into the singleFile collection "logo",
     * replacing an existing logo. Only for the creator or a super admin. The response
     * contains the fresh logo URL.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may replace the logo.')]
    #[ResponseAttribute(status: 422, description: 'Validation error (not an image, wrong MIME type, too large or dimensions too large).')]
    public function uploadLogo(UploadMediaRequest $request, Course $course): CourseResource
    {
        $course->addMedia($request->file('file')->getRealPath())
            ->usingName($course->name)
            ->toMediaCollection('logo');

        return CourseResource::make($course->fresh());
    }
}
