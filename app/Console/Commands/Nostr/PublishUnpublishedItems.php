<?php

namespace App\Console\Commands\Nostr;

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\NostrTrait;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class PublishUnpublishedItems extends Command
{
    use NostrTrait;

    protected $signature = 'nostr:publish {--model=}';

    protected $description = 'Publish unpublished items to Nostr';

    private const TZ_MAP = [
        'de' => 'Europe/Berlin',
        'at' => 'Europe/Berlin',
        'ch' => 'Europe/Berlin',
        'nl' => 'Europe/Amsterdam',
        'hu' => 'Europe/Budapest',
        'pl' => 'Europe/Warsaw',
        'es' => 'Europe/Madrid',
        'pt' => 'Europe/Lisbon',
        'lv' => 'Europe/Riga',
    ];

    private const DOMAIN_MAP = [
        'de' => 'portal.einundzwanzig.space',
        'at' => 'portal.einundzwanzig.space',
        'ch' => 'portal.einundzwanzig.space',
        'nl' => 'portal.eenentwintig.net',
        'hu' => 'portal.huszonegy.world',
        'pl' => 'portal.dwadziesciajeden.pl',
        // Default for other countries (e.g., 'es', 'pt') if added later
        'default' => 'portal.einundzwanzig.space',
    ];

    /**
     * Only the schema gate below returns a non-zero exit code.
     *
     * The other error branches (unsupported model, no text, a rejected publish) kept
     * the exit code 0 they have always had; changing them would change what the
     * scheduler sees for failures this issue never measured. Issue #72 is about the
     * one branch that reports SUCCESS while hiding that it could not even ask the
     * question.
     */
    public function handle(): int
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
                ->where('start', '<=', now()->addDays(7))
                ->orderByDesc('created_at'),
            default => null,
        };

        if (! $query) {
            $this->error("Unsupported model: {$modelName}");

            return self::SUCCESS;
        }

        /*
         * Issue #72: SQLite degrades a double-quoted identifier that matches no column
         * into a STRING LITERAL instead of raising an error, and Laravel quotes
         * identifiers with double quotes. Without `nostr_status` the gate above reads
         * `where 'nostr_status' is null` — never true — so every branch below reports
         * "No unpublished items" and exits 0, indistinguishable from a caught-up
         * system. Ask the schema before asking the data.
         */
        $table = $query->getModel()->getTable();

        if (! Schema::hasColumn($table, 'nostr_status')) {
            $this->error("Missing column: {$table}.nostr_status — run php artisan migrate. Without it this command finds nothing to publish and would exit 0 as if everything were up to date.");

            return self::FAILURE;
        }

        $model = $query->first();

        if (! $model) {
            $this->info("No unpublished items for model: {$modelName}");

            return self::SUCCESS;
        }

        // Get country code
        $countryCode = $this->getCountryCode($model);

        // Set the domain based on country code for URL generation
        $domain = self::DOMAIN_MAP[$countryCode] ?? self::DOMAIN_MAP['default'];
        URL::useOrigin('https://'.$domain); // Forces URL generation to use this domain

        // Configure timezone and locale
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

        return self::SUCCESS;
    }

    /**
     * Issue #76: the stored code is lowercased HERE, where it enters the command,
     * because every use below needs the same lowercase form and a PHP array lookup is
     * case-sensitive — DOMAIN_MAP and TZ_MAP are keyed lowercase, `app.locale` has to
     * be 'nl' to resolve lang/nl.json, and the code is also the `{country:code}`
     * segment of the URL that goes into the published note. The case-insensitive
     * Country::matchingCode() scope added for #58 compares in SQL and reaches none of
     * them. CountryFactory writes uppercase codes, so with a stored 'NL' the note went
     * out with the German domain, Europe/Berlin, and `nostr.meetup_event_text` as its
     * text — the untranslated key, because lang/NL.json does not exist.
     */
    private function getCountryCode(Model $model): string
    {
        $countryCode = match (true) {
            $model instanceof Meetup => $model->city?->country?->code ?? 'de',
            $model instanceof MeetupEvent => $model->meetup?->city?->country?->code ?? 'de',
            $model instanceof Course => $model->lecturer?->country?->code ?? 'de',
            $model instanceof CourseEvent => $model->course?->lecturer?->country?->code ?? 'de',
            default => 'de', // Default fallback
        };

        return mb_strtolower($countryCode);
    }

    private function configureForCountry(string $countryCode): void
    {
        // Set user timezone and locale based on country code
        $timezone = self::TZ_MAP[$countryCode] ?? 'Europe/Berlin';
        config([
            'app.user-timezone' => $timezone,
            'app.locale' => in_array($countryCode, ['at', 'ch']) ? 'de' : $countryCode,
        ]);
    }
}
