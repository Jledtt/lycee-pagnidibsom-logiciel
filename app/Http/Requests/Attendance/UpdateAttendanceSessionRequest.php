<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('attendance.create')) {
            return false;
        }

        Gate::authorize('update', $this->route('attendanceSession'));

        return true;
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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $session = $this->route('attendanceSession');
                $studentIds = collect($this->input('records', []))
                    ->pluck('student_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique();

                if (! $session || $studentIds->isEmpty()) {
                    return;
                }

                $enrolledIds = $session->schoolClass
                    ->enrollments()
                    ->where('academic_year_id', $session->academic_year_id)
                    ->where('status', 'active')
                    ->whereIn('student_id', $studentIds)
                    ->pluck('student_id')
                    ->map(fn ($id) => (int) $id);

                if ($enrolledIds->count() !== $studentIds->count()) {
                    $validator->errors()->add('records', 'Un eleve ne fait pas partie de cette classe.');
                }
            },
        ];
    }
}
