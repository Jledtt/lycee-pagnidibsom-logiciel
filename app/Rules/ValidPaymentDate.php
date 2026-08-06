<?php

namespace App\Rules;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

class ValidPaymentDate implements ValidationRule
{
    public function __construct(private readonly ?User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            $paidAt = CarbonImmutable::parse((string) $value, config('app.timezone'));
        } catch (Throwable) {
            return;
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        if ($paidAt->isAfter($now)) {
            $fail('La date du paiement ne peut pas être dans le futur.');

            return;
        }

        if ($this->user?->can('payments.backdate')) {
            return;
        }

        if ($paidAt->isBefore($now->startOfDay()->subDays(2))) {
            $fail('Sans autorisation d’antidatage, la date du paiement est limitée aux deux derniers jours calendaires.');
        }
    }
}
