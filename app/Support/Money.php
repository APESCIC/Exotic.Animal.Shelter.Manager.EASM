<?php

namespace App\Support;

/**
 * Amounts are stored as whole pence to avoid floating-point rounding.
 */
class Money
{
    public const INPUT_PATTERN = '/^\d{1,7}(\.\d{1,2})?$/';

    public static function format(?int $pence): string
    {
        if ($pence === null) {
            return '—';
        }

        return '£'.number_format($pence / 100, 2);
    }

    public static function plain(int $pence): string
    {
        return number_format($pence / 100, 2, '.', '');
    }

    public static function toPence(string $amount): int
    {
        [$pounds, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');

        return ((int) $pounds * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
