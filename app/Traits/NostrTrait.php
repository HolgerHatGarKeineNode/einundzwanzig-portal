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

    public function getText(Model $model, string $countryCode): string|null
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
        if ($model instanceof Course) {
            return route('courses.landingpage', ['country' => $countryCode, 'course' => $model]);
        } elseif ($model instanceof CourseEvent) {
            return route('courses.landingpage', ['country' => $countryCode, 'course' => $model->course]);
        } elseif ($model instanceof Meetup) {
            return route('meetups.landingpage', ['country' => $countryCode, 'meetup' => $model]);
        } elseif ($model instanceof MeetupEvent) {
            return route('meetups.landingpage-event',
                ['country' => $countryCode, 'meetup' => $model->meetup, 'event' => $model]);
        }

        return '';
    }
}
