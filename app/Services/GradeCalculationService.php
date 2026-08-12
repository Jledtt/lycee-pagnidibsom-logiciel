<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;

class GradeCalculationService
{
    /** @var array<string, int>|null */
    private ?array $canonicalTypeIds = null;

    public function subjectAverage(Student $student, Term $term, int $subjectId, ?int $schoolClassId = null, ?int $termPeriodId = null): ?float
    {
        return $this->subjectAverageDetails($student, $term, $subjectId, $schoolClassId, $termPeriodId)['general'];
    }

    /**
     * @return array{devoir: ?float, composition: ?float, general: ?float}
     */
    public function subjectAverageDetails(Student $student, Term $term, int $subjectId, ?int $schoolClassId = null, ?int $termPeriodId = null): array
    {
        $assessments = Assessment::query()
            ->with(['assessmentType', 'grades' => fn ($query) => $query->where('student_id', $student->id)])
            ->where('term_id', $term->id)
            ->where('subject_id', $subjectId)
            ->when($schoolClassId, fn ($query) => $query->where('school_class_id', $schoolClassId))
            ->when($termPeriodId, fn ($query) => $query->where('term_period_id', $termPeriodId))
            ->get();

        $groups = [];

        foreach ($assessments as $assessment) {
            $assessmentType = $assessment->assessmentType;

            if (
                ! $assessmentType instanceof AssessmentType
                || $assessmentType->status !== 'active'
                || (float) $assessmentType->weight <= 0
            ) {
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

            $groupKey = (int) $assessmentType->id;
            $groups[$groupKey] ??= [
                'scores' => [],
                'weight' => (float) $assessmentType->weight,
            ];
            $groups[$groupKey]['scores'][] = $normalizedScore;
        }

        if (empty($groups)) {
            return $this->emptySubjectAverageDetails();
        }

        $groupAverages = [];
        $weightedTotal = 0.0;
        $presentWeights = 0.0;
        $allScores = [];

        foreach ($groups as $groupId => $group) {
            $groupAverage = array_sum($group['scores']) / count($group['scores']);
            $groupAverages[$groupId] = $groupAverage;
            $weightedTotal += $groupAverage * $group['weight'];
            $presentWeights += $group['weight'];
            array_push($allScores, ...$group['scores']);
        }

        $generalAverage = config('lpp.grades.weighted_averages', true)
            ? $weightedTotal / $presentWeights
            : array_sum($allScores) / count($allScores);

        $canonicalTypeIds = $this->canonicalTypeIds();

        return [
            'devoir' => $this->groupAverage($groupAverages, $canonicalTypeIds[AssessmentType::NAME_DEVOIR] ?? null),
            'composition' => $this->groupAverage($groupAverages, $canonicalTypeIds[AssessmentType::NAME_COMPOSITION] ?? null),
            'general' => round($generalAverage, 2),
        ];
    }

    public function generalAverage(Student $student, SchoolClass $schoolClass, Term $term, ?int $termPeriodId = null): ?float
    {
        return $this->termSummary($student, $schoolClass, $term, $termPeriodId)['general_average'];
    }

    /**
     * @return array{
     *     rows: Collection<int, array{class_subject: ClassSubject, devoir: ?float, composition: ?float, general: ?float, coefficient: float, points: ?float}>,
     *     total_coefficients: float,
     *     total_points: float,
     *     general_average: ?float
     * }
     */
    public function termSummary(Student $student, SchoolClass $schoolClass, Term $term, ?int $termPeriodId = null): array
    {
        $classSubjects = ClassSubject::query()
            ->with(['subject', 'teacher'])
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->get();

        $rows = $classSubjects->map(function (ClassSubject $classSubject) use ($student, $schoolClass, $term, $termPeriodId): array {
            $details = $this->subjectAverageDetails(
                $student,
                $term,
                $classSubject->subject_id,
                $schoolClass->id,
                $termPeriodId,
            );
            $coefficient = (float) $classSubject->coefficient;

            return [
                'class_subject' => $classSubject,
                'devoir' => $details['devoir'],
                'composition' => $details['composition'],
                'general' => $details['general'],
                'coefficient' => $coefficient,
                'points' => $details['general'] === null ? null : round($details['general'] * $coefficient, 2),
            ];
        });

        $ratedRows = $rows->whereNotNull('general');
        $coefficients = (float) $ratedRows->sum('coefficient');
        $totalPoints = round((float) $ratedRows->sum('points'), 2);

        if ($coefficients <= 0) {
            return [
                'rows' => $rows,
                'total_coefficients' => 0.0,
                'total_points' => 0.0,
                'general_average' => null,
            ];
        }

        return [
            'rows' => $rows,
            'total_coefficients' => $coefficients,
            'total_points' => $totalPoints,
            'general_average' => round($totalPoints / $coefficients, 2),
        ];
    }

    /**
     * @param  iterable<array{general: ?float, coefficient: float, points: ?float}>  $rows
     */
    public function familyAverage(iterable $rows): ?float
    {
        $ratedRows = collect($rows)->whereNotNull('general');
        $coefficients = (float) $ratedRows->sum('coefficient');

        if ($coefficients <= 0) {
            return null;
        }

        return round((float) $ratedRows->sum('points') / $coefficients, 2);
    }

    /**
     * @param  array<int, float>  $groupAverages
     */
    private function groupAverage(array $groupAverages, mixed $typeId): ?float
    {
        if ($typeId === null || ! array_key_exists((int) $typeId, $groupAverages)) {
            return null;
        }

        return round($groupAverages[(int) $typeId], 2);
    }

    /** @return array<string, int> */
    private function canonicalTypeIds(): array
    {
        if ($this->canonicalTypeIds === null) {
            $this->canonicalTypeIds = AssessmentType::query()
                ->whereIn('name', [AssessmentType::NAME_DEVOIR, AssessmentType::NAME_COMPOSITION])
                ->pluck('id', 'name')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->canonicalTypeIds;
    }

    /**
     * @return array{devoir: null, composition: null, general: null}
     */
    private function emptySubjectAverageDetails(): array
    {
        return [
            'devoir' => null,
            'composition' => null,
            'general' => null,
        ];
    }
}
