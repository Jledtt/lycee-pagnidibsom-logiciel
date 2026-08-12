<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlausibleStudentBirthDate implements ValidationRule
{
    public const MESSAGE = 'Date de naissance invraisemblable, vérifie la saisie.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isPlausible($value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function isPlausible(mixed $value, ?CarbonImmutable $today = null): bool
    {
        try {
            $birthDate = CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $today ??= CarbonImmutable::today();

        return $birthDate->betweenIncluded($today->subYears(30), $today->subYears(6));
    }
}
