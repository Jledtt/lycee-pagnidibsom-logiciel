<?php

namespace App\Services;

use App\Models\Payment;

class ReceiptNumberService
{
    public function generate(): string
    {
        $prefix = 'REC-' . now()->format('Ymd') . '-';

        $lastPayment = Payment::query()
            ->where('receipt_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($lastPayment !== null) {
            $lastNumber = (int) substr($lastPayment->receipt_number, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
