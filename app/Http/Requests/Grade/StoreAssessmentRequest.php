<?php

namespace App\Http\Requests\Grade;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('grades.create') ?? false;
    }

    public function rules(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        return [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'term_id' => [
                'required',
                $academicYear
                    ? Rule::exists('terms', 'id')->where('academic_year_id', $academicYear->id)
                    : 'exists:terms,id',
            ],
            'subject_id' => ['required', 'exists:subjects,id'],
            'assessment_type_id' => ['required', 'exists:assessment_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'assessment_date' => ['nullable', 'date'],
        ];
    }
}
