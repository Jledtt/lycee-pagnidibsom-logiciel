<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTimetablePeriodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periods' => ['required', 'array', 'min:1', 'max:20'],
            'periods.*.id' => ['nullable', 'integer', 'exists:timetable_periods,id'],
            'periods.*.sort_order' => ['required', 'integer', 'min:1', 'max:99', 'distinct'],
            'periods.*.label' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'periods.*.starts_at' => ['nullable', 'date_format:H:i'],
            'periods.*.ends_at' => ['nullable', 'date_format:H:i'],
            'periods.*.is_break' => ['required', 'boolean'],
            'periods.*.is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $periods = collect($this->input('periods', []));

            foreach ($periods as $index => $period) {
                if ((bool) ($period['is_break'] ?? false)) {
                    continue;
                }

                if (blank($period['starts_at'] ?? null) || blank($period['ends_at'] ?? null)) {
                    $validator->errors()->add("periods.$index.starts_at", 'Les heures de début et de fin sont obligatoires pour un cours.');

                    continue;
                }

                if ($period['ends_at'] <= $period['starts_at']) {
                    $validator->errors()->add("periods.$index.ends_at", 'La fin du créneau doit être postérieure au début.');
                }
            }

            $courses = $periods
                ->filter(fn (array $period) => ! (bool) ($period['is_break'] ?? false) && (bool) ($period['is_active'] ?? false))
                ->sortBy('starts_at')
                ->values();

            for ($index = 1; $index < $courses->count(); $index++) {
                $previous = $courses[$index - 1];
                $current = $courses[$index];

                if (($current['starts_at'] ?? '') < ($previous['ends_at'] ?? '')) {
                    $validator->errors()->add('periods', "Les créneaux {$previous['label']} et {$current['label']} se chevauchent.");
                }
            }
        }];
    }
}
