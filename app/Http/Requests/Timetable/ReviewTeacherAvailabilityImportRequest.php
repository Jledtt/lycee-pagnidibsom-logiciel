<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;

class ReviewTeacherAvailabilityImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('timetables.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'max:1000'],
            'rows.*' => ['required', 'array'],
            'rows.*.line' => ['nullable', 'integer', 'min:1'],
            'rows.*.selected' => ['required', 'boolean'],
            'rows.*.teacher_id' => ['nullable', 'integer'],
            'rows.*.day' => ['nullable', 'string', 'max:20'],
            'rows.*.starts_at' => ['nullable', 'string', 'max:10'],
            'rows.*.ends_at' => ['nullable', 'string', 'max:10'],
            'rows.*.availability_status' => ['nullable', 'string', 'max:20'],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
            'rows.*.raw' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
