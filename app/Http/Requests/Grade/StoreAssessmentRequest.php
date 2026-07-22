<?php

namespace App\Http\Requests\Grade;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\TermPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('grades.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'entry_mode' => $this->input('entry_mode', Assessment::ENTRY_MODE_STANDARD),
        ]);
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
            'term_period_id' => ['nullable', 'exists:term_periods,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'assessment_type_id' => ['required', Rule::exists('assessment_types', 'id')->where('status', 'active')],
            'entry_mode' => ['required', Rule::in(array_keys(Assessment::entryModeLabels()))],
            'title' => ['required', 'string', 'max:255'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'assessment_date' => ['nullable', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $termPeriodId = $this->integer('term_period_id');

                if (! $termPeriodId) {
                    return;
                }

                $period = TermPeriod::query()->find($termPeriodId);

                if ($period && (int) $period->term_id !== $this->integer('term_id')) {
                    $validator->errors()->add('term_period_id', 'La periode choisie ne correspond pas au trimestre.');
                }
            },
        ];
    }
}
