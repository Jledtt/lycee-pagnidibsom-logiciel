<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class AuditTrailService
{
    public function record(
        string $action,
        Model $model,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null
    ): void {
        if (! Auth::check()) {
            return;
        }

        $label = $this->label($model);
        $description ??= $this->description($action, $model, $label);

        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => (string) $model->getKey(),
            'auditable_label' => $label,
            'description' => $description,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
        ]);

        try {
            activity('lpp')
                ->causedBy(Auth::user())
                ->performedOn($model)
                ->event($action)
                ->withProperties([
                    'ancien' => $oldValues,
                    'nouveau' => $newValues,
                    'element' => $label,
                    'module' => class_basename($model),
                    'ip' => request()?->ip(),
                    'navigateur' => Str::limit((string) request()?->userAgent(), 500, ''),
                ])
                ->log($description);
        } catch (Throwable) {
            // Le journal interne reste disponible si Spatie ne peut pas ecrire.
        }
    }

    public function label(Model $model): string
    {
        $student = $this->relatedStudent($model);

        if ($student) {
            return $this->studentLabel($student);
        }

        foreach (['full_name', 'name', 'receipt_number', 'matricule', 'title', 'code'] as $field) {
            $value = data_get($model, $field);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    public function description(string $action, Model $model, ?string $label = null): string
    {
        $labels = [
            'created' => 'Creation',
            'updated' => 'Modification',
            'deleted' => 'Suppression',
            'permissions_updated' => 'Modification des acces',
            'roles_updated' => 'Modification du role',
        ];

        return ($labels[$action] ?? ucfirst($action)).' - '.class_basename($model).' - '.($label ?? $this->label($model));
    }

    private function relatedStudent(Model $model): ?Student
    {
        try {
            if ($model instanceof Student) {
                return $model;
            }

            if (method_exists($model, 'student')) {
                $student = $model->student;

                if ($student instanceof Student) {
                    return $student;
                }
            }

            if (method_exists($model, 'enrollment')) {
                $student = $model->enrollment?->student;

                if ($student instanceof Student) {
                    return $student;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function studentLabel(Student $student): string
    {
        $name = trim((string) ($student->full_name ?? ''));
        $matricule = trim((string) ($student->matricule ?? ''));

        return trim($name.($matricule !== '' ? ' - '.$matricule : '')) ?: 'Eleve #'.$student->getKey();
    }
}
