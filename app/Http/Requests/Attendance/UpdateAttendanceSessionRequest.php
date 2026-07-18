<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'exists:students,id'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
            'records.*.minutes_late' => ['nullable', 'integer', 'min:0', 'max:600'],
            'records.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
