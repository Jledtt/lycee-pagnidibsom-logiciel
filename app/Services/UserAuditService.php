<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserAuditService
{
    public function recordPasswordChanged(Request $request, User $user): ActivityLog
    {
        return $this->record($request, $user, $user, 'password_changed', 'Changement du mot de passe personnel');
    }

    public function recordPasswordReset(Request $request, User $actor, User $target): ActivityLog
    {
        return $this->record($request, $actor, $target, 'password_reset', 'Réinitialisation du mot de passe');
    }

    private function record(Request $request, User $actor, User $target, string $action, string $label): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => (string) $target->id,
            'auditable_label' => $target->name,
            'description' => $label.' - User - '.$target->name,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }
}
