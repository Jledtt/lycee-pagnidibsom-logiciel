<?php

namespace App\Http\Requests\ReportCard;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportCardSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('report_cards.view') ?? false;
    }

    public function rules(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        return [
            'school_class_id' => [
                'required',
                $academicYear
                    ? Rule::exists('school_classes', 'id')->where('academic_year_id', $academicYear->id)
                    : 'exists:school_classes,id',
            ],
            'term_id' => [
                'required',
                $academicYear
                    ? Rule::exists('terms', 'id')->where('academic_year_id', $academicYear->id)
                    : 'exists:terms,id',
            ],
        ];
    }
}
