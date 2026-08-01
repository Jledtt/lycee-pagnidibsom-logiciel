<?php

namespace App\Http\Requests\Teacher;

use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherWorkSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('teacher_attendance.manage') ?? false;
    }

    public function rules(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        $classRule = Rule::exists('school_classes', 'id')
            ->where('academic_year_id', $academicYear?->id ?? 0);
        $dateRules = ['required', 'date'];

        if ($academicYear) {
            $dateRules[] = 'after_or_equal:'.$academicYear->starts_at->toDateString();
            $dateRules[] = 'before_or_equal:'.$academicYear->ends_at->toDateString();
        }

        return [
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'school_class_id' => ['required', 'integer', $classRule],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'session_date' => $dateRules,
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'hours_worked' => ['required', 'numeric', 'min:0.25', 'max:250'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['required', Rule::in(['draft', 'validated'])],
            'teacher_signed' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        return [
            'session_date.after_or_equal' => $academicYear
                ? 'La date du cours doit être comprise dans l’année scolaire '.$academicYear->name.', à partir du '.$academicYear->starts_at->format('d/m/Y').'.'
                : 'La date du cours est antérieure à la période autorisée.',
            'session_date.before_or_equal' => $academicYear
                ? 'La date du cours doit être comprise dans l’année scolaire '.$academicYear->name.', au plus tard le '.$academicYear->ends_at->format('d/m/Y').'.'
                : 'La date du cours dépasse la période autorisée.',
        ];
    }

    public function attributes(): array
    {
        return [
            'session_date' => 'date du cours',
            'starts_at' => 'heure de début',
            'ends_at' => 'heure de fin',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->session()->flash('teacher_work_session_open', true);

        parent::failedValidation($validator);
    }
}
