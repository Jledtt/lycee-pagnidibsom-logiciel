<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayTeacherFeeStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('teacher_fees.pay') ?? false;
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['Espèces', 'Virement', 'Mobile Money', 'Chèque'])],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->session()->flash('teacher_fee_payment_open', true);

        parent::failedValidation($validator);
    }
}
