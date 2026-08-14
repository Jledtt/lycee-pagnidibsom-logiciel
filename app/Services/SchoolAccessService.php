<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AttendanceSession;
use App\Models\ClassSubject;
use App\Models\Student;
use App\Models\StudentExitAuthorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SchoolAccessService
{
    private const FULL_STUDENT_RECORD_ROLES = ['admin', 'direction', 'secretariat'];

    private const GLOBAL_STUDENT_IDENTITY_ROLES = ['admin', 'direction', 'secretariat', 'comptable', 'surveillant'];

    private const GLOBAL_GRADE_ROLES = ['admin', 'direction'];

    private const GLOBAL_ATTENDANCE_ROLES = ['admin', 'direction', 'surveillant'];

    public function canViewFullStudentRecord(User $user): bool
    {
        return $user->hasAnyRole(self::FULL_STUDENT_RECORD_ROLES);
    }

    public function canViewStudentDocuments(User $user): bool
    {
        return $this->canViewFullStudentRecord($user);
    }

    public function canViewStudent(User $user, Student $student): bool
    {
        if ($user->hasAnyRole(self::GLOBAL_STUDENT_IDENTITY_ROLES)) {
            return true;
        }

        if (! $user->hasRole('enseignant')) {
            return false;
        }

        return $student->enrollments()
            ->where('enrollments.status', 'active')
            ->whereIn('school_class_id', $this->teacherClassIdsQuery($user))
            ->exists();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeStudents(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(self::GLOBAL_STUDENT_IDENTITY_ROLES)) {
            return $query;
        }

        if (! $user->hasRole('enseignant')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('enrollments', function (Builder $enrollments) use ($user): void {
            $enrollments
                ->where('enrollments.status', 'active')
                ->whereIn('school_class_id', $this->teacherClassIdsQuery($user));
        });
    }

    public function canAccessAssessment(User $user, Assessment $assessment): bool
    {
        if ($user->hasAnyRole(self::GLOBAL_GRADE_ROLES)) {
            return true;
        }

        return $user->hasRole('enseignant')
            && $this->teacherHasClassSubject(
                $user,
                (int) $assessment->school_class_id,
                (int) $assessment->subject_id,
            );
    }

    public function canManageClassSubject(User $user, int $schoolClassId, int $subjectId): bool
    {
        if ($user->hasAnyRole(self::GLOBAL_GRADE_ROLES)) {
            return true;
        }

        return $user->hasRole('enseignant')
            && $this->teacherHasClassSubject($user, $schoolClassId, $subjectId);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeAssessments(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(self::GLOBAL_GRADE_ROLES)) {
            return $query;
        }

        if (! $user->hasRole('enseignant')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($subQuery) use ($user): void {
            $subQuery
                ->selectRaw('1')
                ->from('class_subjects')
                ->whereColumn('class_subjects.school_class_id', 'assessments.school_class_id')
                ->whereColumn('class_subjects.subject_id', 'assessments.subject_id')
                ->where('class_subjects.teacher_id', $user->id)
                ->where('class_subjects.is_active', true);
        });
    }

    public function canAccessAttendanceClass(User $user, int $schoolClassId): bool
    {
        if ($user->hasAnyRole(self::GLOBAL_ATTENDANCE_ROLES)) {
            return true;
        }

        return $user->hasRole('enseignant')
            && ClassSubject::query()
                ->where('school_class_id', $schoolClassId)
                ->where('teacher_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }

    public function canAccessAttendanceSession(User $user, AttendanceSession $session): bool
    {
        return $this->canAccessAttendanceClass($user, (int) $session->school_class_id);
    }

    public function canAccessStudentExitAuthorization(User $user, StudentExitAuthorization $authorization): bool
    {
        if ($user->hasAnyRole(['admin', 'direction', 'secretariat', 'surveillant'])) {
            return true;
        }

        return $user->hasRole('enseignant')
            && $this->canAccessAttendanceClass($user, (int) $authorization->school_class_id);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeStudentExitAuthorizations(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'direction', 'secretariat', 'surveillant'])) {
            return $query;
        }

        if (! $user->hasRole('enseignant')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('student_exit_authorizations.school_class_id', $this->teacherClassIdsQuery($user));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeClasses(Builder $query, User $user, string $area): Builder
    {
        $globalRoles = $area === 'grades'
            ? self::GLOBAL_GRADE_ROLES
            : self::GLOBAL_ATTENDANCE_ROLES;

        if ($user->hasAnyRole($globalRoles)) {
            return $query;
        }

        if (! $user->hasRole('enseignant')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('school_classes.id', $this->teacherClassIdsQuery($user));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeAttendanceSessions(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(self::GLOBAL_ATTENDANCE_ROLES)) {
            return $query;
        }

        if (! $user->hasRole('enseignant')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('attendance_sessions.school_class_id', $this->teacherClassIdsQuery($user));
    }

    private function teacherHasClassSubject(User $user, int $schoolClassId, int $subjectId): bool
    {
        return ClassSubject::query()
            ->where('school_class_id', $schoolClassId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    /** @return Builder<ClassSubject> */
    private function teacherClassIdsQuery(User $user): Builder
    {
        return ClassSubject::query()
            ->select('school_class_id')
            ->where('teacher_id', $user->id)
            ->where('is_active', true);
    }
}
