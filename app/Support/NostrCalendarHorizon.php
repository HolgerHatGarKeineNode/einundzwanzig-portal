<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * How far ahead a date may start to be sent to the relays at all — defined ONCE.
 *
 * The operator's rule of 2026-11-17: nothing about the past, and nothing further ahead
 * than one year. Recurring series reach as far as 2034; of 654 upcoming dates 310
 * started more than a year ahead, and a one-off repair run sent 31 of those before it
 * was stopped. A date is in the window when
 *
 *     now < start <= now + services.nostr.calendar_horizon_days
 *
 * Every place that sends a kind 31923, and every place that decides whether a meetup's
 * kind 31924 calendar is sent or which dates it lists, asks this class. A date further
 * ahead is not lost: it enters the window by itself as time passes and is published
 * then.
 */
final class NostrCalendarHorizon
{
    public static function days(): int
    {
        return max(1, (int) config('services.nostr.calendar_horizon_days', 365));
    }

    public static function end(): CarbonInterface
    {
        return now()->addDays(self::days());
    }

    /**
     * Constrain a query on `meetup_events` to the window.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function constrain($query, string $column = 'start')
    {
        return $query->where($column, '>', now())->where($column, '<=', self::end());
    }
}
