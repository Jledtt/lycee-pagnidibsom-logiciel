<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ResetStaffPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reset-staff-password', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ];
    }
}
