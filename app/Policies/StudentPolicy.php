<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Services\SchoolAccessService;
use Illuminate\Auth\Access\Response;

class StudentPolicy
{
    public function __construct(private readonly SchoolAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    public function view(User $user, Student $student): Response
    {
        return $user->can('students.view') && $this->access->canViewStudent($user, $student)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function viewFullRecord(User $user, Student $student): Response
    {
        return $this->access->canViewStudent($user, $student)
            && $this->access->canViewFullStudentRecord($user)
                ? Response::allow()
                : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->can('students.create');
    }

    public function update(User $user, Student $student): Response
    {
        return $user->can('students.update')
            && $this->access->canViewFullStudentRecord($user)
            && $this->access->canViewStudent($user, $student)
                ? Response::allow()
                : Response::denyAsNotFound();
    }

    public function delete(User $user, Student $student): Response
    {
        return $user->can('students.delete')
            && $this->access->canViewFullStudentRecord($user)
            && $this->access->canViewStudent($user, $student)
                ? Response::allow()
                : Response::denyAsNotFound();
    }
}
