<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function enroll(
        Student $student,
        SchoolClass $schoolClass,
        AcademicYear $academicYear,
        array $data = []
    ): Enrollment {
        if ((int) $schoolClass->academic_year_id !== (int) $academicYear->id) {
            throw ValidationException::withMessages([
                'school_class_id' => 'La classe choisie n’appartient pas à cette année scolaire.',
            ]);
        }

        return DB::transaction(function () use ($student, $schoolClass, $academicYear, $data) {
            $exists = Enrollment::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('student_id', $student->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'student_id' => 'Cet élève est déjà inscrit pour cette année scolaire.',
                ]);
            }

            return Enrollment::create([
                'academic_year_id' => $academicYear->id,
                'student_id' => $student->id,
                'school_class_id' => $schoolClass->id,
                'enrollment_date' => $data['enrollment_date'] ?? now()->toDateString(),
                'type' => $data['type'] ?? 'new',
                'status' => $data['status'] ?? 'active',
                'previous_school' => $data['previous_school'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
        });
    }
}
