<?php

namespace App\Policies;

use App\Models\StudentExitAuthorization;
use App\Models\User;
use App\Services\SchoolAccessService;
use Illuminate\Auth\Access\Response;

class StudentExitAuthorizationPolicy
{
    public function __construct(private readonly SchoolAccessService $access) {}

    public function view(User $user, StudentExitAuthorization $authorization): Response
    {
        return $this->access->canAccessStudentExitAuthorization($user, $authorization)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
