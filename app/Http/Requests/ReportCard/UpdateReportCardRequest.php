<?php

namespace App\Http\Requests\ReportCard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('report_cards.validate') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['nullable', 'string', 'max:255'],
            'principal_observation' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'validated', 'published'])],
        ];
    }
}
