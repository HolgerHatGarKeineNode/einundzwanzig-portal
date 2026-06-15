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
     */
    public const MAX_OCCURRENCES = 100;

    /**
     * @return array<int, Carbon>
     */
    public function handle(
        CarbonInterface $start,
        CarbonInterface $end,
        RecurrenceType $type,
        ?string $dayOfWeek = null,
        ?string $dayPosition = null,
    ): array {
        $start = $start->copy();
        $end = $end->copy();

        if ($dayOfWeek && $dayPosition) {
            return $this->customRecurrence($start, $end, $dayOfWeek, $dayPosition);
        }

        if ($type === RecurrenceType::Weekly && $dayOfWeek) {
            $dayOfWeekNumber = self::dayOfWeekNumber($dayOfWeek);

            if ($dayOfWeekNumber !== null) {
                $cursor = $start->copy();

                while ($cursor->dayOfWeek !== $dayOfWeekNumber) {
                    $cursor->addDay();
                }

                return $this->collect($cursor, $end, fn (Carbon $date) => $date->addWeek());
            }
        }

        return $this->collect(
            $start,
            $end,
            fn (Carbon $date) => $type === RecurrenceType::Weekly ? $date->addWeek() : $date->addMonth(),
        );
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
    private function customRecurrence(CarbonInterface $start, CarbonInterface $end, string $dayOfWeek, string $dayPosition): array
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

                $cursor = $cursor->copy()->addMonth();
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
