<?php

namespace App\Services;

use App\Models\Payment;

class ReceiptNumberService
{
    public function __construct(private readonly OfficialNumberService $officialNumberService)
    {
    }

    public function generate(): string
    {
        return $this->officialNumberService->generate(
            OfficialNumberService::PAYMENT_RECEIPT,
            fn (string $number) => Payment::query()->where('receipt_number', $number)->exists(),
        );
    }
}
