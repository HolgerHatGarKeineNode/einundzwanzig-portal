<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Application date implementation, bound via Date::use() in AppServiceProvider.
 *
 * All formatters render ISO 8601 with 24-hour time, in the timezone resolved by
 * the SetTimezone middleware into config('app.user-timezone'). ISO output is
 * deliberately locale-independent: the portal serves nine locales, so no month
 * or day name may leak into a numeric date.
 *
 * The explicit ->timezone(config('app.user-timezone')) is what does the converting,
 * and it is not redundant: date_default_timezone_set() runs at bootstrap, long
 * before SetTimezone, so PHP's default zone stays UTC for the whole request
 * (measured after a real request with a New York user: date_default_timezone_get()
 * = 'UTC'). Note that config('app.timezone') is NOT UTC at that point — SetTimezone
 * overwrites it with the user's zone too — but nothing reads it here, and a
 * console or queue context never runs that middleware at all. Drop the explicit
 * ->timezone() call and every timestamp falls back to UTC.
 */
class Carbon extends CarbonImmutable
{
    public function asDate(): string
    {
        return $this->timezone(config('app.user-timezone'))
            ->format('Y-m-d');
    }

    public function asTime(): string
    {
        return $this->timezone(config('app.user-timezone'))
            ->format('H:i');
    }

    public function asDateTime(): string
    {
        $dt = $this->timezone(config('app.user-timezone'));

        return sprintf('%s (%s)',
            $dt->format('Y-m-d H:i'),
            $dt->timezoneAbbreviatedName
        );
    }
}
