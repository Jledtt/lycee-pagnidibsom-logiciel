<?php

namespace Tests\Unit;

use App\Models\MockExamSubject;
use PHPUnit\Framework\TestCase;

class MockExamSubjectFeeTest extends TestCase
{
    public function test_it_calculates_gross_and_net_teacher_fees(): void
    {
        $subject = new MockExamSubject([
            'fee_quantity' => 12,
            'fee_rate' => 1500,
            'fee_withholding_amount' => 900,
            'fee_advance_amount' => 2000,
            'fee_other_deduction_amount' => 100,
        ]);

        $this->assertSame(18000.0, $subject->calculatedFeeGrossAmount());
        $this->assertSame(15000.0, $subject->calculatedFeeNetAmount());
    }

    public function test_explicit_gross_amount_takes_priority_over_quantity_times_rate(): void
    {
        $subject = new MockExamSubject([
            'fee_quantity' => 12,
            'fee_rate' => 1500,
            'fee_amount' => 20000,
        ]);

        $this->assertSame(20000.0, $subject->calculatedFeeGrossAmount());
        $this->assertSame(20000.0, $subject->calculatedFeeNetAmount());
    }
}
