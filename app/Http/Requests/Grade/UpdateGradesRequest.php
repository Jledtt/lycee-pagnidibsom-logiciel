<?php

namespace App\Http\Requests\Grade;

use App\Models\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:'.(float) $assessment->max_score],
            'grades.*.is_absent' => ['nullable', 'boolean'],
            'grades.*.status' => ['nullable', Rule::in(array_keys(Grade::statusLabels()))],
            'grades.*.comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
