<?php

namespace App\Policies;

use App\Models\StudentDocument;
use App\Models\User;
use App\Services\SchoolAccessService;
use Illuminate\Auth\Access\Response;

class StudentDocumentPolicy
{
    public function __construct(private readonly SchoolAccessService $access) {}

    public function view(User $user, StudentDocument $document): Response
    {
        return $user->can('students.view') && $this->access->canViewStudentDocuments($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): Response
    {
        return $user->can('students.update') && $this->access->canViewStudentDocuments($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, StudentDocument $document): Response
    {
        return $user->can('students.update') && $this->access->canViewStudentDocuments($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
