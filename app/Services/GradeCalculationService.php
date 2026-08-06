<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;

class GradeCalculationService
{
    public function subjectAverage(Student $student, Term $term, int $subjectId, ?int $schoolClassId = null, ?int $termPeriodId = null): ?float
    {
        $assessments = Assessment::query()
            ->with(['assessmentType', 'grades' => fn ($query) => $query->where('student_id', $student->id)])
            ->where('term_id', $term->id)
            ->where('subject_id', $subjectId)
            ->when($schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId))
            ->when($termPeriodId, fn ($query) => $query->where('term_period_id', $termPeriodId))
            ->get();

        $scores = [];
        $groups = [];

        foreach ($assessments as $assessment) {
            $assessmentType = $assessment->assessmentType;

            if (! $assessmentType instanceof AssessmentType || $assessmentType->status !== 'active') {
                continue;
            }

            $grade = $assessment->grades->first();

            if ($grade === null || ! $grade->isCounted() || $grade->score === null) {
                continue;
            }

            $maxScore = (float) $assessment->max_score;

            if ($maxScore <= 0) {
                continue;
            }

            $normalizedScore = ((float) $grade->score / $maxScore) * 20;
            $scores[] = $normalizedScore;

            $weight = (float) $assessmentType->weight;

            if ($weight <= 0) {
                continue;
            }

            $groupKey = (int) $assessmentType->id;
            $groups[$groupKey] ??= [
                'scores' => [],
                'weight' => $weight,
            ];
            $groups[$groupKey]['scores'][] = $normalizedScore;
        }

        if (! config('lpp.grades.weighted_averages', true)) {
            if (empty($scores)) {
                return null;
            }

            return round(array_sum($scores) / count($scores), 2);
        }

        if (empty($groups)) {
            return null;
        }

        $weightedTotal = 0.0;
        $presentWeights = 0.0;

        foreach ($groups as $group) {
            $groupAverage = array_sum($group['scores']) / count($group['scores']);
            $weightedTotal += $groupAverage * $group['weight'];
            $presentWeights += $group['weight'];
        }

        return round($weightedTotal / $presentWeights, 2);
    }

    public function generalAverage(Student $student, SchoolClass $schoolClass, Term $term, ?int $termPeriodId = null): ?float
    {
        $classSubjects = ClassSubject::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->get();

        $weightedTotal = 0.0;
        $coefficients = 0.0;

        foreach ($classSubjects as $classSubject) {
            $average = $this->subjectAverage($student, $term, $classSubject->subject_id, $schoolClass->id, $termPeriodId);

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
