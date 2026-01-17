<?php

namespace App\Enums;

enum RecurrenceType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => __('Täglich'),
            self::Weekly => __('Wöchentlich'),
            self::Monthly => __('Monatlich'),
            self::Yearly => __('Jährlich'),
            self::Custom => __('Benutzerdefiniert'),
        };
    }
}
