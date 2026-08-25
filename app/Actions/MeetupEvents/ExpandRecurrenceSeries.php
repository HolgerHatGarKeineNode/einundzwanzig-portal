<?php

namespace App\Actions\MeetupEvents;

use App\Enums\RecurrenceType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;

/**
 * Expands a recurrence rule into the concrete list of start datetimes.
 *
 * This is the single source of truth shared by the Livewire event editor
 * (preview + persist) and the REST API. It is timezone-agnostic: it operates
 * on the Carbon instances it receives and preserves their timezone, leaving
 * any UTC normalization to the caller.
 */
class ExpandRecurrenceSeries
{
    /**
     * Hard upper bound on the number of generated occurrences.
     *
     * This is a hard cut, not a warning: {@see self::collect()} stops here and the
     * remainder of the series is never created. The form says so in as many words.
     */
    public const MAX_OCCURRENCES = 100;

    /**
     * Expand a rule into concrete start datetimes.
     *
     * `$interval` is the number of units between two occurrences — 2 with a weekly
     * type means fortnightly. Values below 1 are raised to 1: an interval of 0 would
     * advance the cursor by nothing and fill the whole allowance with the same date.
     *
     * @param  int|null  $interval  Units between repeats; null and 0 both mean 1.
     * @return array<int, Carbon>
     */
    public function handle(
        CarbonInterface $start,
        CarbonInterface $end,
        RecurrenceType $type,
        ?string $dayOfWeek = null,
        ?string $dayPosition = null,
        ?int $interval = null,
    ): array {
        $start = $start->copy();
        $end = $end->copy();
        $interval = max(1, $interval ?? 1);

        if ($dayOfWeek && $dayPosition) {
            return $this->customRecurrence($start, $end, $dayOfWeek, $dayPosition, $interval);
        }

        if ($type === RecurrenceType::Weekly && $dayOfWeek) {
            $dayOfWeekNumber = self::dayOfWeekNumber($dayOfWeek);

            if ($dayOfWeekNumber !== null) {
                $cursor = $start->copy();

                while ($cursor->dayOfWeek !== $dayOfWeekNumber) {
                    $cursor->addDay();
                }

                return $this->collect($cursor, $end, $this->advanceFor($type, $interval));
            }
        }

        return $this->collect($start, $end, $this->advanceFor($type, $interval));
    }

    /**
     * How one occurrence advances to the next, per recurrence type.
     *
     * Every case of {@see RecurrenceType} is answered here. Until P5 only `Weekly` was
     * treated separately and everything else fell through to `addMonth()` — which made
     * `daily` produce monthly dates while both the REST API and the MCP tool happily
     * accepted the type.
     *
     * `Custom` advances monthly on purpose: it is the "third Friday of the month"
     * pattern, and without a weekday/position pair (which routes to
     * {@see self::customRecurrence()}) there is nothing custom left to honour.
     *
     * @return Closure(Carbon): mixed
     */
    private function advanceFor(RecurrenceType $type, int $interval): Closure
    {
        return match ($type) {
            RecurrenceType::Daily => fn (Carbon $date) => $date->addDays($interval),
            RecurrenceType::Weekly => fn (Carbon $date) => $date->addWeeks($interval),
            RecurrenceType::Yearly => fn (Carbon $date) => $date->addYears($interval),
            RecurrenceType::Monthly, RecurrenceType::Custom => fn (Carbon $date) => $date->addMonths($interval),
        };
    }

    /**
     * @param  Closure(Carbon): mixed  $advance
     * @return array<int, Carbon>
     */
    private function collect(CarbonInterface $cursor, CarbonInterface $end, Closure $advance): array
    {
        $dates = [];
        $current = $cursor->copy();

        while ($current->lessThanOrEqualTo($end) && count($dates) < self::MAX_OCCURRENCES) {
            $dates[] = $current->copy();
            $advance($current);
        }

        return $dates;
    }

    /**
     * @return array<int, Carbon>
     */
    private function customRecurrence(CarbonInterface $start, CarbonInterface $end, string $dayOfWeek, string $dayPosition, int $interval = 1): array
    {
        $dates = [];
        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end) && count($dates) < self::MAX_OCCURRENCES) {
            $occurrence = $this->findOccurrence($cursor, $dayOfWeek, $dayPosition);

            if ($occurrence && $occurrence->lessThanOrEqualTo($end)) {
                $occurrenceWithTime = $occurrence->copy()->setTimeFrom($start);

                if ($occurrenceWithTime->greaterThanOrEqualTo($start)) {
                    $dates[] = $occurrenceWithTime;
                }

                $cursor = $cursor->copy()->addMonths($interval);
            } else {
                break;
            }
        }

        return $dates;
    }

    private function findOccurrence(CarbonInterface $monthCursor, string $dayOfWeek, string $dayPosition): ?Carbon
    {
        $dayOfWeekNumber = self::dayOfWeekNumber($dayOfWeek);
        $dayPositionNumber = self::dayPositionNumber($dayPosition);

        if ($dayOfWeekNumber === null || $dayPositionNumber === null) {
            return $monthCursor->copy();
        }

        $date = $monthCursor->copy()->startOfMonth();

        if ($dayPositionNumber === -1) {
            return $date->lastOfMonth($dayOfWeekNumber)
                ->setTime($monthCursor->hour, $monthCursor->minute, $monthCursor->second);
        }

        $count = 0;

        while ($date->month === $monthCursor->month) {
            if ($date->dayOfWeek === $dayOfWeekNumber) {
                $count++;

                if ($count === $dayPositionNumber) {
                    return $date->copy()
                        ->setTime($monthCursor->hour, $monthCursor->minute, $monthCursor->second);
                }
            }

            $date->addDay();
        }

        return null;
    }

    private static function dayOfWeekNumber(string $day): ?int
    {
        return match (strtolower($day)) {
            'monday', 'montag' => Carbon::MONDAY,
            'tuesday', 'dienstag' => Carbon::TUESDAY,
            'wednesday', 'mittwoch' => Carbon::WEDNESDAY,
            'thursday', 'donnerstag' => Carbon::THURSDAY,
            'friday', 'freitag' => Carbon::FRIDAY,
            'saturday', 'samstag' => Carbon::SATURDAY,
            'sunday', 'sonntag' => Carbon::SUNDAY,
            default => null,
        };
    }

    private static function dayPositionNumber(string $position): ?int
    {
        return match (strtolower($position)) {
            'first', 'erster' => 1,
            'second', 'zweiter' => 2,
            'third', 'dritter' => 3,
            'fourth', 'vierter' => 4,
            'last', 'letzter' => -1,
            default => null,
        };
    }
}
