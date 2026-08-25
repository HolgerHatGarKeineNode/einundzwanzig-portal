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

it('expands a daily series daily', function () {
    // Vor P5 kannte handle() nur `Weekly` gesondert; alles andere fiel auf addMonth()
    // zurück, und `daily` erzeugte damit monatliche Termine — während sowohl
    // StoreMeetupEventRequest als auch das MCP-Tool den Typ annahmen.
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2026-07-07 18:00:00'),
        RecurrenceType::Daily,
    );

    expect($dates)->toHaveCount(7)
        ->and($dates[1]->format('Y-m-d'))->toBe('2026-07-02')
        ->and($dates[6]->format('Y-m-d'))->toBe('2026-07-07');
});

it('expands a yearly series yearly', function () {
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2029-07-01 18:00:00'),
        RecurrenceType::Yearly,
    );

    expect($dates)->toHaveCount(4)
        ->and($dates[1]->format('Y-m-d'))->toBe('2027-07-01')
        ->and($dates[3]->format('Y-m-d'))->toBe('2029-07-01');
});

it('treats a custom type without a weekday pair as monthly', function () {
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2026-10-01 18:00:00'),
        RecurrenceType::Custom,
    );

    expect($dates)->toHaveCount(4)
        ->and($dates[1]->format('Y-m-d'))->toBe('2026-08-01');
});

it('spaces a weekly series by the given interval', function () {
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2026-07-29 18:00:00'),
        RecurrenceType::Weekly,
        null,
        null,
        2,
    );

    expect($dates)->toHaveCount(3)
        ->and($dates[0]->format('Y-m-d'))->toBe('2026-07-01')
        ->and($dates[1]->format('Y-m-d'))->toBe('2026-07-15')
        ->and($dates[2]->format('Y-m-d'))->toBe('2026-07-29');
});

it('applies the interval to a weekday-shifted weekly series too', function () {
    // 2026-07-01 ist ein Mittwoch; erster Freitag ist der 03.07.
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2026-08-01 18:00:00'),
        RecurrenceType::Weekly,
        'friday',
        null,
        2,
    );

    expect($dates)->toHaveCount(3)
        ->and($dates[0]->format('Y-m-d'))->toBe('2026-07-03')
        ->and($dates[1]->format('Y-m-d'))->toBe('2026-07-17')
        ->and($dates[2]->format('Y-m-d'))->toBe('2026-07-31');
});

it('applies the interval to a custom weekday-position rule', function () {
    // Jeden zweiten Monat der letzte Freitag: 2026-07-31, 2026-09-25, 2026-11-27.
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 19:00:00'),
        Carbon::parse('2026-12-31 19:00:00'),
        RecurrenceType::Monthly,
        'friday',
        'last',
        2,
    );

    expect($dates)->toHaveCount(3)
        ->and($dates[0]->format('Y-m-d'))->toBe('2026-07-31')
        ->and($dates[1]->format('Y-m-d'))->toBe('2026-09-25')
        ->and($dates[2]->format('Y-m-d'))->toBe('2026-11-27');
});

it('raises an interval below one to one instead of repeating the same date', function () {
    // Die Validierung weist 0 ab; die Action ist die zweite Verteidigungslinie, denn
    // MAX_OCCURRENCES würde sonst 100-mal denselben Termin durchlassen.
    $dates = $this->action->handle(
        Carbon::parse('2026-07-01 18:00:00'),
        Carbon::parse('2026-07-15 18:00:00'),
        RecurrenceType::Weekly,
        null,
        null,
        0,
    );

    expect($dates)->toHaveCount(3)
        ->and($dates[1]->format('Y-m-d'))->toBe('2026-07-08');
});
