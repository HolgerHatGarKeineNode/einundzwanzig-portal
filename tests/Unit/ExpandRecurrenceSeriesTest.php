<?php

use App\Actions\MeetupEvents\ExpandRecurrenceSeries;
use App\Enums\RecurrenceType;
use Carbon\Carbon;

beforeEach(function () {
    $this->action = new ExpandRecurrenceSeries;
});

it('expands a basic weekly series', function () {
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2026-07-29 18:00:00'),
        RecurrenceType::Weekly,
    );

    expect($dates)->toHaveCount(5)
        ->and($dates[0]->format('Y-m-d H:i'))->toBe('2026-07-01 18:00')
        ->and($dates[4]->format('Y-m-d H:i'))->toBe('2026-07-29 18:00');
});

it('expands a basic monthly series', function () {
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2026-10-01 18:00:00'),
        RecurrenceType::Monthly,
    );

    expect($dates)->toHaveCount(4)
        ->and($dates[1]->format('Y-m-d'))->toBe('2026-08-01');
});

it('shifts a weekly series to the requested weekday', function () {
    // 2026-07-01 is a Wednesday; ask for Friday occurrences.
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2026-07-31 18:00:00'),
        RecurrenceType::Weekly,
        'friday',
    );

    expect($dates)->not->toBeEmpty();
    foreach ($dates as $date) {
        expect($date->dayOfWeek)->toBe(Carbon::FRIDAY);
    }
    // First Friday on/after 2026-07-01 is 2026-07-03.
    expect($dates[0]->format('Y-m-d'))->toBe('2026-07-03');
});

it('expands a custom "last Friday of the month" rule', function () {
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 19:00:00'),
        Carbon::parse('2026-09-30 19:00:00'),
        RecurrenceType::Monthly,
        'friday',
        'last',
    );

    // Last Fridays: 2026-07-31, 2026-08-28, 2026-09-25
    expect($dates)->toHaveCount(3)
        ->and($dates[0]->format('Y-m-d'))->toBe('2026-07-31')
        ->and($dates[1]->format('Y-m-d'))->toBe('2026-08-28')
        ->and($dates[2]->format('Y-m-d'))->toBe('2026-09-25')
        ->and($dates[0]->format('H:i'))->toBe('19:00');
});

it('enforces the hard cap of 100 occurrences', function () {
    $dates = $this->action->handle(
        Carbon::parse('2026-01-01 18:00:00'),
        Carbon::parse('2030-01-01 18:00:00'),
        RecurrenceType::Weekly,
    );

    expect($dates)->toHaveCount(ExpandRecurrenceSeries::MAX_OCCURRENCES);
});
