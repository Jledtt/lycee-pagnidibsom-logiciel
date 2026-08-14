<?php

namespace App\Policies;

use App\Models\DisciplinaryRecord;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DisciplinaryRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('discipline.view');
    }

    public function view(User $user, DisciplinaryRecord $record): Response
    {
        return $user->can('discipline.view')
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->can('discipline.manage');
    }

    public function update(User $user, DisciplinaryRecord $record): Response
    {
        return $user->can('discipline.manage')
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
