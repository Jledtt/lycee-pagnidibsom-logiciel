<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogObserver
{
    private const IGNORED_FIELDS = [
        'created_at',
        'updated_at',
        'deleted_at',
        'email_verified_at',
        'last_login_at',
        'password',
        'remember_token',
    ];

    public function created(Model $model): void
    {
        $this->record('created', $model, [], $this->clean($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->clean($model->getChanges());

        if ($changes === []) {
            return;
        }

        $oldValues = [];
        foreach (array_keys($changes) as $field) {
            $oldValues[$field] = $model->getOriginal($field);
        }

        $this->record('updated', $model, $this->clean($oldValues), $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $this->clean($model->getOriginal()), []);
    }

    private function record(string $action, Model $model, array $oldValues, array $newValues): void
    {
        if (! Auth::check()) {
            return;
        }

        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => (string) $model->getKey(),
            'auditable_label' => $this->label($model),
            'description' => $this->description($action, $model),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
        ]);
    }

    private function clean(array $values): array
    {
        return Arr::except($values, self::IGNORED_FIELDS);
    }

    private function label(Model $model): string
    {
        foreach (['full_name', 'name', 'receipt_number', 'matricule', 'title', 'code'] as $field) {
            $value = data_get($model, $field);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($model) . ' #' . $model->getKey();
    }

    private function description(string $action, Model $model): string
    {
        $labels = [
            'created' => 'Creation',
            'updated' => 'Modification',
            'deleted' => 'Suppression',
        ];

        return ($labels[$action] ?? ucfirst($action)) . ' - ' . class_basename($model) . ' - ' . $this->label($model);
    }
}
