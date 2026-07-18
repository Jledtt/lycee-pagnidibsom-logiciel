<?php

namespace App\Http\Requests\Grade;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('grades.update') ?? false;
    }

    public function rules(): array
    {
        $assessment = $this->route('assessment');

        return [
            'grades' => ['nullable', 'array'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:' . (float) $assessment->max_score],
            'grades.*.is_absent' => ['nullable', 'boolean'],
            'grades.*.comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
