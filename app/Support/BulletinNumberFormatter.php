<?php

namespace App\Support;

final class BulletinNumberFormatter
{
    public static function decimal(mixed $value): string
    {
        return $value === null ? '-' : number_format((float) $value, 2, '.', '');
    }

    public static function coefficient(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        $coefficient = (float) $value;

        return floor($coefficient) === $coefficient
            ? number_format($coefficient, 0, '.', '')
            : number_format($coefficient, 2, '.', '');
    }
}
