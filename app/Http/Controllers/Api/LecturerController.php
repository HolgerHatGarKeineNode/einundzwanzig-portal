<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltersNumericIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLecturerRequest;
use App\Http\Requests\Api\UpdateLecturerRequest;
use App\Http\Requests\Api\UploadMediaRequest;
use App\Http\Resources\LecturerResource;
use App\Models\Course;
use App\Models\Lecturer;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'Lecturers', weight: 4)]
class LecturerController extends Controller
{
    use FiltersNumericIds;

    /**
     * List and search lecturers
     *
     * Public endpoint; returns id and name, sorted alphabetically. Without the 'selected' parameter the list is capped at 10 items. Every lecturer additionally contains an 'image' (avatar thumbnail URL). With 'withDetails' the limit is dropped and every lecturer additionally contains subtitle, future_events_count (number of upcoming course events) and next_event (date of the next upcoming course event, or null).
     */
    #[QueryParameter(name: 'search', description: 'Substring search in the name.', required: false, type: 'string')]
    #[QueryParameter(name: 'selected', description: 'Loads exactly the given IDs.', required: false, type: 'array')]
    #[QueryParameter(name: 'withDetails', description: 'Presence flag: also returns subtitle, future_events_count and next_event and lifts the 10-item limit.', required: false, type: 'string')]
    public function index(Request $request)
    {
        $withDetails = $request->exists('withDetails');

        return Lecturer::query()
            ->select($withDetails ? ['id', 'name', 'subtitle'] : ['id', 'name'])
            ->with('media')
            ->orderBy('name')
            ->when($withDetails, fn (Builder $query) => $query
                ->withCount(['coursesEvents as future_events_count' => fn (Builder $events) => $events->where('from', '>=', now())])
                ->withMin(['coursesEvents as next_event' => fn (Builder $events) => $events->where('from', '>=', now())], 'from'))
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
            ->map(function (Lecturer $lecturer) {
                $lecturer->image = $lecturer->getFirstMediaUrl('avatar',
                    'thumb');

                return $lecturer;
            });
    }

    /**
     * Show a lecturer
     *
     * Public endpoint; returns the profile of a lecturer (avatar, subtitle,
     * intro, description, Nostr and web links) including their courses with their
     * next upcoming course event.
     *
     * @return array<string, mixed>
     */
    public function show(Lecturer $lecturer): array
    {
        $lecturer->load([
            'media',
            'courses' => fn ($query) => $query
                ->orderBy('name')
                ->with('media')
                ->withMin(['courseEvents as next_event' => fn (Builder $events) => $events->where('from', '>=', now())], 'from'),
        ]);

        return [
            'id' => $lecturer->id,
            'name' => $lecturer->name,
            'subtitle' => $lecturer->subtitle,
            'intro' => $lecturer->intro,
            'description' => $lecturer->description,
            'image' => $lecturer->getFirstMediaUrl('avatar', 'preview'),
            'active' => (bool) $lecturer->active,
            'nostr' => $lecturer->nostr,
            'website' => $lecturer->website,
            'twitter_username' => $lecturer->twitter_username,
            'lightning_address' => $lecturer->lightning_address,
            'courses' => $lecturer->courses->map(fn (Course $course) => [
                'id' => $course->id,
                'name' => $course->name,
                'image' => $course->getFirstMediaUrl('logo', 'thumb'),
                'next_event' => $course->next_event,
            ])->all(),
        ];
    }

    /**
     * Create a lecturer
     *
     * Allows an authenticated user to create a lecturer programmatically.
     * The creator (created_by) is set automatically.
     */
    #[ResponseAttribute(status: 401, description: 'Not authenticated.')]
    #[ResponseAttribute(status: 422, description: 'Validation error.')]
    public function store(StoreLecturerRequest $request): JsonResponse
    {
        $lecturer = Lecturer::create($request->validated());

        return LecturerResource::make($lecturer->fresh())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a lecturer
     *
     * Updates a lecturer; only for the creator or a super admin.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may change the lecturer.')]
    #[ResponseAttribute(status: 422, description: 'Validation error.')]
    public function update(UpdateLecturerRequest $request, Lecturer $lecturer): LecturerResource
    {
        $lecturer->update($request->validated());

        return LecturerResource::make($lecturer->fresh());
    }

    /**
     * List own lecturers
     *
     * Returns all lecturers created by the authenticated user, sorted alphabetically.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lecturer::class);

        $lecturers = Lecturer::query()
            ->with('media')
            ->where('created_by', $request->user()->id)
            ->orderBy('name')
            ->get();

        return LecturerResource::collection($lecturers);
    }

    /**
     * Show own lecturer
     *
     * Shows a single lecturer created by the authenticated user.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may view the lecturer.')]
    public function mineShow(Lecturer $lecturer): LecturerResource
    {
        Gate::authorize('view', $lecturer);

        return LecturerResource::make($lecturer);
    }

    /**
     * Upload a lecturer avatar
     *
     * Uploads an avatar (multipart, field "file") into the singleFile collection "avatar",
     * replacing an existing image. Only for the creator or a super admin. The
     * response contains the fresh avatar URL.
     */
    #[ResponseAttribute(status: 403, description: 'Only the creator or a super admin may replace the avatar.')]
    #[ResponseAttribute(status: 422, description: 'Validation error (not an image, wrong MIME type, too large or dimensions too large).')]
    public function uploadAvatar(UploadMediaRequest $request, Lecturer $lecturer): LecturerResource
    {
        $lecturer->addMedia($request->file('file')->getRealPath())
            ->usingName($lecturer->name)
            ->toMediaCollection('avatar');

        return LecturerResource::make($lecturer->fresh());
    }
}
