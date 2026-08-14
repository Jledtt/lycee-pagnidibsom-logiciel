<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\SchoolAccessService;
use Illuminate\Auth\Access\Response;

class AttendanceSessionPolicy
{
    public function __construct(private readonly SchoolAccessService $access) {}

    public function view(User $user, AttendanceSession $session): Response
    {
        return $user->can('attendance.view') && $this->access->canAccessAttendanceSession($user, $session)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, AttendanceSession $session): Response
    {
        return $user->can('attendance.create') && $this->access->canAccessAttendanceSession($user, $session)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
