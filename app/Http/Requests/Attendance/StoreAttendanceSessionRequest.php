<?php

namespace App\Http\Requests\Attendance;

use App\Services\SchoolAccessService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('attendance.create')) {
            return false;
        }

        abort_unless(
            app(SchoolAccessService::class)->canAccessAttendanceClass(
                $this->user(),
                $this->integer('school_class_id'),
            ),
            404,
        );

        return true;
    }

    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'session_date' => ['required', 'date'],
        ];
    }
}
