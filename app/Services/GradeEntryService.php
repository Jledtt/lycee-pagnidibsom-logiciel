<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Collection;

class GradeEntryService
{
    public function createAssessment(AcademicYear $academicYear, array $data, User $teacher): Assessment
    {
        return Assessment::query()->create([
            ...$data,
            'academic_year_id' => $academicYear->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function updateGrades(Assessment $assessment, array $grades, User $user): void
    {
        $studentIds = Enrollment::query()
            ->where('academic_year_id', $assessment->academic_year_id)
            ->where('school_class_id', $assessment->school_class_id)
            ->where('status', 'active')
            ->pluck('student_id')
            ->all();

        foreach ($studentIds as $studentId) {
            $line = $grades[$studentId] ?? [];
            $isAbsent = (bool) ($line['is_absent'] ?? false);
            $score = $isAbsent ? null : ($line['score'] ?? null);

            Grade::query()->updateOrCreate(
                [
                    'assessment_id' => $assessment->id,
                    'student_id' => $studentId,
                ],
                [
                    'score' => $score,
                    'is_absent' => $isAbsent,
                    'comment' => $line['comment'] ?? null,
                    'entered_by' => $user->id,
                ],
            );
        }
    }

    public function studentsForClass(int $academicYearId, int $schoolClassId): Collection
    {
        return Enrollment::query()
            ->with('student')
            ->where('academic_year_id', $academicYearId)
            ->where('school_class_id', $schoolClassId)
            ->where('enrollments.status', 'active')
            ->whereHas('student', fn ($query) => $query->where('students.status', 'active'))
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('enrollments.*')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
    }

    public function subjectBelongsToClass(int $schoolClassId, int $subjectId): bool
    {
        return ClassSubject::query()
            ->where('school_class_id', $schoolClassId)
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->exists();
    }

    public function classTermIsLocked(int $schoolClassId, int $termId): bool
    {
        $total = Assessment::query()
            ->where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->count();

        if ($total <= 0) {
            return false;
        }

        return Assessment::query()
            ->where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->where('is_locked', true)
            ->count() === $total;
    }
}
