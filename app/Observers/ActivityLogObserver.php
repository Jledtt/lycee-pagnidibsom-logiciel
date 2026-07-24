<?php

namespace App\Observers;

use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

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
        app(AuditTrailService::class)->record($action, $model, $oldValues, $newValues);
    }

    private function clean(array $values): array
    {
        return Arr::except($values, self::IGNORED_FIELDS);
    }

}
