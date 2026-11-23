<?php

namespace App\Console\Commands\Nostr;

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\NostrTrait;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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
     * Country codes whose language file carries a different name than the country.
     *
     * Everything not listed here is tried as a locale of its own name, and a code
     * without a lang/<code>.json falls back to `app.fallback_locale` — see
     * localeForCountry().
     */
    private const LOCALE_MAP = [
        'at' => 'de',
        'ch' => 'de',
        'cz' => 'cs',
    ];

    /**
     * Issue #84: every branch that prints an error now ends the run non-zero.
     *
     * Until #84 only the schema gate from #72 did, so a publish that failed on every
     * single run was invisible: routes/console.php schedules this hourly (MeetupEvent)
     * and daily at 18:00 (Meetup), and ScheduleRunCommand::runEvent judges a run by its
     * exit code alone — at 0 it raises nothing, dispatches no ScheduledTaskFailed and
     * reports nothing, so the scheduler recorded a failing publisher as a success.
     *
     * TWO codes, not three, because a code only earns its place if a caller would act
     * differently on it:
     *
     * - INVALID (2) — the invocation is wrong: `--model` names something this command
     *   cannot query. No retry helps; a human has to fix the caller (routes/console.php
     *   or the command line). This is what Symfony reserves 2 for, and it is the one
     *   distinction worth making here: a configuration error, not a run failure.
     * - FAILURE (1) — the invocation was right and the run did not publish: the schema
     *   gate below, a record that yielded no text, or a publish that was refused. The
     *   next scheduled run may well succeed, and #72's gate already uses this code.
     *
     * "No text generated" shares FAILURE with a refused publish instead of getting a
     * third code, because the operator's next step is identical for both — read the
     * error line, which names the model. A code nobody branches on is decoration.
     *
     * SUCCESS (0) still means "nothing to do": an empty queue is the normal state of an
     * hourly run, and alarming on a healthy idle run would be worse than the defect
     * this fixes.
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

            return self::INVALID;
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
                return $this->failRun("Failed to publish for {$modelName}: ".$result['errorOutput'], $model);
            }
        } else {
            return $this->failRun("No text generated for {$modelName}", $model);
        }

        return self::SUCCESS;
    }

    /**
     * Ends a run that had a record to publish and did not publish it.
     *
     * The scheduler only sees the exit code: Nightwatch issues #44 and #47 recorded
     * "failed with exit code [1]" 30+ times and nothing else, because the error line
     * went to the console of a scheduled run, which nobody keeps. So the same message
     * is also logged, with the record it was about.
     *
     * Logs reach Nightwatch only through its `nightwatch` channel, and whether the
     * default stack contains it is decided by LOG_STACK on the server, not here. The
     * channel is therefore added explicitly unless the default stack already has it,
     * so the reason shows up beside the failed task without being logged twice.
     */
    private function failRun(string $message, Model $model): int
    {
        // noscl ends its stderr with a newline; a log line should not.
        $message = trim($message);

        $this->error($message);

        $defaultChannel = config('logging.default');
        $defaultChannels = config("logging.channels.{$defaultChannel}.driver") === 'stack'
            ? (array) config("logging.channels.{$defaultChannel}.channels", [])
            : [$defaultChannel];

        $channels = [$defaultChannel];

        if (config('logging.channels.nightwatch') !== null && ! in_array('nightwatch', $defaultChannels, true)) {
            $channels[] = 'nightwatch';
        }

        Log::stack($channels)->warning($message, [
            'command' => $this->getName(),
            'model' => $model::class,
            'id' => $model->getKey(),
        ]);

        return self::FAILURE;
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
        config(['app.user-timezone' => $timezone]);

        // setLocale() rather than config(['app.locale' => …]): it also reaches a
        // translator that was resolved before this point, which a config write does not.
        app()->setLocale($this->localeForCountry($countryCode));
    }

    /**
     * The note's text locale for a country code.
     *
     * Until this fix the country code itself became the locale, and Laravel never
     * falls back for JSON translations: Translator::get() looks the key up in the
     * JSON file of the requested locale only, and returns the bare key when that
     * file does not exist. A meetup in France, the US, the UK, Italy or Czechia
     * (code `cz`, file `cs.json`) therefore published `nostr.meetup_event_text`
     * as its note.
     */
    private function localeForCountry(string $countryCode): string
    {
        $locale = self::LOCALE_MAP[$countryCode] ?? $countryCode;

        return is_file(lang_path($locale.'.json')) ? $locale : config('app.fallback_locale');
    }
}
