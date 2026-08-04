<?php

namespace App\Http\Requests\Timetable;

use App\Models\TeacherAvailability;
use App\Models\TeacherAvailabilitySchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workflow_status' => [
                'required',
                Rule::in([
                    TeacherAvailabilitySchedule::STATUS_DRAFT,
                    TeacherAvailabilitySchedule::STATUS_SUBMITTED,
                    TeacherAvailabilitySchedule::STATUS_VALIDATED,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'slots' => ['required', 'array'],
            'slots.*' => ['required', 'array'],
            'slots.*.*' => [
                'required',
                Rule::in([
                    TeacherAvailability::STATUS_UNAVAILABLE,
                    TeacherAvailability::STATUS_AVAILABLE,
                    TeacherAvailability::STATUS_PREFERRED,
                ]),
            ],
        ];
    }
}
