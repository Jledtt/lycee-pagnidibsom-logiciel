<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;
use App\Services\SchoolAccessService;
use Illuminate\Auth\Access\Response;

class AssessmentPolicy
{
    public function __construct(private readonly SchoolAccessService $access) {}

    public function view(User $user, Assessment $assessment): Response
    {
        return $user->can('grades.view') && $this->access->canAccessAssessment($user, $assessment)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Assessment $assessment): Response
    {
        return $user->can('grades.update') && $this->access->canAccessAssessment($user, $assessment)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, Assessment $assessment): Response
    {
        return $this->update($user, $assessment);
    }

    public function lock(User $user, Assessment $assessment): Response
    {
        return $user->can('grades.lock') && $this->access->canAccessAssessment($user, $assessment)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function unlock(User $user, Assessment $assessment): Response
    {
        return $user->can('grades.unlock') && $this->access->canAccessAssessment($user, $assessment)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
