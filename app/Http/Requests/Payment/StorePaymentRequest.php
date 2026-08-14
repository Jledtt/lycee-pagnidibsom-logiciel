<?php

namespace App\Http\Requests\Payment;

use App\Models\AcademicYear;
use App\Rules\ValidPaymentDate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.create') ?? false;
    }

    public function rules(): array
    {
        $academicYearId = AcademicYear::query()
            ->where('is_active', true)
            ->value('id');

        return [
            'student_id' => ['required', 'exists:students,id'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank_transfer,other'],
            'paid_at' => ['nullable', 'date', new ValidPaymentDate($this->user())],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array'],
            'lines.*.fee_type_id' => ['nullable', 'exists:fee_types,id'],
            'lines.*.fee_schedule_id' => [
                'nullable',
                Rule::exists('fee_schedules', 'id')
                    ->where('academic_year_id', $academicYearId ?? 0),
            ],
            'lines.*.amount' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->session()->flash('payment_form_open', true);

        parent::failedValidation($validator);
    }
}
