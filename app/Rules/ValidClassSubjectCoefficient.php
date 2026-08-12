<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidClassSubjectCoefficient implements ValidationRule
{
    public const MESSAGE = 'Coefficient invalide : utilise un pas de 0,5 entre 0,5 et 10.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function isValid(mixed $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        $coefficient = (float) $value;

        return $coefficient >= 0.5
            && $coefficient <= 10
            && abs(($coefficient * 2) - round($coefficient * 2)) < 0.00001;
    }
}
