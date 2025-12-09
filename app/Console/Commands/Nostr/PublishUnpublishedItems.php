<?php

namespace App\Console\Commands\Nostr;

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\NostrTrait;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class PublishUnpublishedItems extends Command
{
    use NostrTrait;

    protected $signature = 'nostr:publish {--model=}';
    protected $description = 'Publish unpublished items to Nostr';

    private const TZ_MAP = [
        'de' => 'Europe/Berlin',
        'nl' => 'Europe/Amsterdam',
        'hu' => 'Europe/Budapest',
        'pl' => 'Europe/Warsaw',
        'es' => 'Europe/Madrid',
        'pt' => 'Europe/Lisbon',
    ];

    public function handle(): void
    {
        $modelName = $this->option('model');
        $modelClass = '\\App\\Models\\'.$modelName;

        // Define query logic per model type
        $query = match ($modelName) {
            'Course' => $modelClass::whereNull('nostr_status')->orderByDesc('created_at'),
            'CourseEvent' => $modelClass::whereNull('nostr_status')
                ->where('from', '>', now())
                ->orderByDesc('created_at'),
            'Meetup' => $modelClass::with('city.country')
                ->whereNull('nostr_status')
                ->orderByDesc('created_at'),
            'MeetupEvent' => $modelClass::with('meetup.city.country')
                ->whereNull('nostr_status')
                ->where('start', '>', now())
                ->orderByDesc('created_at'),
            default => null,
        };

        if (!$query) {
            $this->error("Unsupported model: {$modelName}");
            return;
        }

        $model = $query->first();

        if (!$model) {
            $this->info("No unpublished items for model: {$modelName}");
            return;
        }

        // Get country code and configure timezone/locale if applicable
        $countryCode = $this->getCountryCode($model);
        $this->configureForCountry($countryCode);

        $text = $this->getText($model, $countryCode);
        if ($text) {
            $result = $this->publishOnNostr($model, $text);
            if ($result['success']) {
                $this->info("Published successfully for {$modelName}");
            } else {
                $this->error("Failed to publish for {$modelName}: ".$result['errorOutput']);
            }
        } else {
            $this->error("No text generated for {$modelName}");
        }
    }

    private function getCountryCode(Model $model): string
    {
        return match (true) {
            $model instanceof Meetup => $model->city?->country?->code ?? 'de',
            $model instanceof MeetupEvent => $model->meetup?->city?->country?->code ?? 'de',
            $model instanceof Course => $model->lecturer?->country?->code ?? 'de',
            $model instanceof CourseEvent => $model->course?->lecturer?->country?->code ?? 'de',
            default => 'de', // Default fallback
        };
    }

    private function configureForCountry(string $countryCode): void
    {
        // Set user timezone and locale based on country code
        $timezone = self::TZ_MAP[$countryCode] ?? 'UTC';
        config([
            'app.user-timezone' => $timezone,
            'app.locale' => $countryCode,
        ]);
    }
}
