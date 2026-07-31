<?php

namespace App\Http\Requests\Attendance;

use App\Models\AttendanceRecord;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class JustifyAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Indique le motif présenté pour justifier cette absence ou ce retard.',
            'reason.min' => 'Le motif doit contenir au moins 3 caractères.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $record = $this->route('attendanceRecord');
        $this->session()->flash(
            'attendance_record_open',
            $record instanceof AttendanceRecord ? $record->id : null,
        );

        parent::failedValidation($validator);
    }
}
