<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;

class GradeCalculationService
{
    public function subjectAverage(Student $student, Term $term, int $subjectId, ?int $schoolClassId = null): ?float
    {
        $assessments = Assessment::query()
            ->with(['assessmentType', 'grades' => fn ($query) => $query->where('student_id', $student->id)])
            ->where('term_id', $term->id)
            ->where('subject_id', $subjectId)
            ->when($schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId))
            ->get();

        $weightedTotal = 0.0;
        $weights = 0.0;

        foreach ($assessments as $assessment) {
            $grade = $assessment->grades->first();

            if ($grade === null || $grade->is_absent || $grade->score === null) {
                continue;
            }

            $normalizedScore = ((float) $grade->score / (float) $assessment->max_score) * 20;
            $weight = (float) $assessment->assessmentType->weight;
            $weightedTotal += $normalizedScore * $weight;
            $weights += $weight;
        }

        if ($weights <= 0) {
            return null;
        }

        return round($weightedTotal / $weights, 2);
    }

    public function generalAverage(Student $student, SchoolClass $schoolClass, Term $term): ?float
    {
        $classSubjects = ClassSubject::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->get();

        $weightedTotal = 0.0;
        $coefficients = 0.0;

        foreach ($classSubjects as $classSubject) {
            $average = $this->subjectAverage($student, $term, $classSubject->subject_id, $schoolClass->id);

            if ($average === null) {
                continue;
            }

            $coefficient = (float) $classSubject->coefficient;
            $weightedTotal += $average * $coefficient;
            $coefficients += $coefficient;
        }

        if ($coefficients <= 0) {
            return null;
        }

        return round($weightedTotal / $coefficients, 2);
    }
}
