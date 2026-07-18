<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    public function rules(): array
    {
        $staffUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staffUser)],
            'username' => ['required', 'string', 'max:80', Rule::unique('users', 'username')->ignore($staffUser)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$staffUser instanceof User ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in($this->roleNames())],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }

    private function roleNames(): array
    {
        return ['admin', 'direction', 'secretariat', 'comptable', 'enseignant', 'surveillant'];
    }
}
