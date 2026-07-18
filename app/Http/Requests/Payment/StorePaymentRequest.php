<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank_transfer,other'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array'],
            'lines.*.fee_type_id' => ['nullable', 'exists:fee_types,id'],
            'lines.*.fee_schedule_id' => ['nullable', 'exists:fee_schedules,id'],
            'lines.*.amount' => ['nullable', 'numeric', 'min:1'],
        ];
    }
}
