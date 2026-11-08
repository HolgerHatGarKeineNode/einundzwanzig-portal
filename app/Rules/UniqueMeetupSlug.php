<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Meetup;
use App\Models\MeetupSlugRedirect;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A meetup URL slug that is well-formed and free (issue #167).
 *
 * Free means two things, and the second is the one a plain `unique:meetups,slug`
 * would miss: no other meetup carries it today, AND no other meetup gave it up
 * earlier. A retired slug still redirects to its old meetup; handing it to a
 * second one would silently take that redirect away and send every shared link
 * of the first meetup to the wrong page.
 *
 * The meetup's own retired slugs are allowed: taking one back is undoing a rename.
 */
class UniqueMeetupSlug implements ValidationRule
{
    /**
     * Lowercase ASCII words joined by single hyphens — what Str::slug() produces,
     * and the only shape that survives being typed, printed and pasted unchanged.
     */
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Slugs a route registered ahead of `/meetup/{meetup:slug}` already answers
     * (`/meetup/meetup-events`, routes/web.php) — a meetup under one of them
     * could never be reached.
     *
     * @var list<string>
     */
    public const RESERVED = ['meetup-events'];

    public function __construct(private readonly ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (preg_match(self::PATTERN, $value) !== 1) {
            $fail(__('Nur Kleinbuchstaben (a–z), Ziffern und einzelne Bindestriche, ohne Bindestrich am Anfang oder Ende.'));

            return;
        }

        if (in_array($value, self::RESERVED, true)) {
            $fail(__('Diese Adresse ist für das Portal reserviert.'));

            return;
        }

        $current = Meetup::query()
            ->where('slug', $value)
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->first(['id', 'name']);

        if ($current !== null) {
            $fail(__('Diese Adresse gehört bereits zum Meetup :name.', ['name' => $current->name]));

            return;
        }

        $retired = MeetupSlugRedirect::query()
            ->with('meetup:id,name')
            ->where('slug', $value)
            ->when($this->ignoreId !== null, fn ($query) => $query->where('meetup_id', '<>', $this->ignoreId))
            ->first();

        if ($retired !== null) {
            $fail(__('Diese Adresse hat früher das Meetup :name verwendet und leitet noch dorthin weiter.', [
                'name' => $retired->meetup?->name ?? '?',
            ]));
        }
    }
}
