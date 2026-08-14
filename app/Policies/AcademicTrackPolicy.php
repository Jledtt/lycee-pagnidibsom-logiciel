<?php

namespace App\Policies;

use App\Models\AcademicTrack;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AcademicTrackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academic_tracks.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academic_tracks.manage');
    }

    public function update(User $user, AcademicTrack $academicTrack): Response
    {
        return $user->can('academic_tracks.manage')
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
