<?php

namespace App\Traits;

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Process;

trait NostrTrait
{
    public function publishOnNostr(Model $model, string $text): array
    {
        if (app()->environment('local')) {
            return [
                'success' => true,
                'output' => 'local',
                'exitCode' => 0,
                'errorOutput' => '',
            ];
        }

        // Use array to pass arguments safely and avoid shell injection
        $result = Process::timeout(60 * 5)
            ->run(['noscl', 'publish', $text]);

        if ($result->successful()) {
            $model->nostr_status = trim($result->output());
            $model->save();
        }

        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'exitCode' => $result->exitCode(),
            'errorOutput' => $result->errorOutput(),
        ];
    }

    public function getText(Model $model, string $countryCode): ?string
    {
        return match (true) {
            $model instanceof CourseEvent => __('nostr.course_event_text', [
                'from' => $this->getFrom($model),
                'name' => $model->course->name,
                'description' => str($model->course->description)->toString(),
                'url' => $this->getUrl($model, $countryCode),
            ]),
            $model instanceof MeetupEvent => __('nostr.meetup_event_text', [
                'from' => $this->getFrom($model),
                /*
                 * asDateTime() feeds the text of a published kind:1 note, not a page.
                 * The ISO 8601 switch (issue #48) therefore changed the shape of every
                 * note this trait publishes from now on — "08.10.2026 19:00 (CEST)"
                 * became "2026-10-08 19:00 (CEST)". Notes already on the relays keep
                 * the old shape; a Nostr event is immutable, so the two forms coexist
                 * in the wild forever and any consumer parsing this string sees both.
                 *
                 * Recorded because nothing guards it: `grep -rn
                 * 'meetup_event_text\|publishOnNostr' tests/` finds no coverage at all,
                 * so a future change to the formatters will alter published note text
                 * again with no test going red.
                 */
                'start' => $model->start->asDateTime(),
                'location' => $model->location,
                'url' => $this->getUrl($model, $countryCode),
            ]),
            $model instanceof Meetup => __('nostr.meetup_text', [
                'from' => $this->getFrom($model),
                'url' => $this->getUrl($model, $countryCode),
            ]),
            $model instanceof Course => __('nostr.course_text', [
                'from' => $this->getFrom($model),
                'name' => $model->name,
                'description' => str($model->description)->toString(),
                'url' => $this->getUrl($model, $countryCode),
            ]),
            default => null,
        };
    }

    private function getFrom(Model $model): string
    {
        if ($model instanceof Course) {
            return $model->lecturer->nostr ? '@'.$model->lecturer->nostr : $model->lecturer->name;
        } elseif ($model instanceof CourseEvent) {
            return $this->getFrom($model->course);
        } elseif ($model instanceof Meetup) {
            return $model->name.($model->nostr ? ' @'.$model->nostr : '');
        } elseif ($model instanceof MeetupEvent) {
            return $this->getFrom($model->meetup);
        }

        return '';
    }

    private function getUrl(Model $model, string $countryCode): string
    {
        return match (true) {
            $model instanceof Course => url()->route('courses.landingpage',
                ['country' => $countryCode, 'course' => $model]),
            $model instanceof CourseEvent => url()->route('courses.landingpage',
                ['country' => $countryCode, 'course' => $model->course]),
            $model instanceof Meetup => url()->route('meetups.landingpage',
                ['country' => $countryCode, 'meetup' => $model]),
            $model instanceof MeetupEvent => url()->route('meetups.landingpage-event',
                ['country' => $countryCode, 'meetup' => $model->meetup, 'event' => $model]),
            default => '',
        };
    }
}
