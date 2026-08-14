<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\ReportCard;
use App\Models\Subject;
use App\Models\Term;
use LogicException;

/**
 * @phpstan-type BulletinSubjectRow array{
 *     subject: Subject,
 *     coefficient: float,
 *     devoir_average: float|null,
 *     composition_average: float|null,
 *     average: float|null,
 *     points: float|null,
 *     appreciation: string,
 *     teacher: string
 * }
 * @phpstan-type BulletinSubjectGroup array{
 *     name: string,
 *     rows: list<BulletinSubjectRow>,
 *     average: float|null
 * }
 */
class BulletinDataService
{
    private const SUBJECT_GROUPS = [
        'Matières littéraires' => ['FR', 'ANG', 'ALL', 'HG', 'PHILO'],
        'Matières scientifiques' => ['MATH', 'SVT', 'PC'],
        'Matières complémentaires' => ['EPS', 'ECM', 'TIC', 'ART', 'TECH'],
    ];

    public function __construct(
        private readonly GradeCalculationService $gradeCalculationService,
        private readonly ReportCardService $reportCardService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $annualSummary
     * @return array<string, mixed>
     */
    public function for(ReportCard $reportCard, ?array $annualSummary = null): array
    {
        $reportCard->loadMissing(['academicYear', 'term', 'student', 'schoolClass.level']);
        $summary = $this->gradeCalculationService->termSummary(
            $reportCard->student,
            $reportCard->schoolClass,
            $reportCard->term,
        );
        $rows = collect($summary['rows'])
            ->sortBy(fn (array $row): string => $row['class_subject']->subject->name)
            ->map(fn (array $row): array => $this->subjectRow($row))
            ->values();
        $termPosition = $this->reportCardService->termPosition($reportCard->term);

        return [
            'annualSummary' => $termPosition >= 3
                ? ($annualSummary ?? $this->reportCardService->annualSummariesForClass($reportCard->schoolClass)[$reportCard->student_id] ?? null)
                : null,
            'classStats' => $this->classStats($reportCard),
            'generatedAt' => now(),
            'recalls' => $this->recalls($reportCard, $termPosition),
            'reportCard' => $reportCard,
            'subjectGroups' => $this->subjectGroups($rows),
            'subjectRows' => $rows,
            'termPosition' => $termPosition,
            'totalCoefficients' => $summary['total_coefficients'],
            'totalPoints' => $summary['total_points'],
        ];
    }

    /**
     * @param  iterable<BulletinSubjectRow>  $rows
     * @return list<BulletinSubjectGroup>
     */
    private function subjectGroups(iterable $rows): array
    {
        $rows = collect($rows);
        $groups = [];

        foreach (self::SUBJECT_GROUPS as $name => $codes) {
            $groupRows = $rows
                ->filter(fn (array $row): bool => in_array($row['subject']->code, $codes, true))
                ->values()
                ->all();

            if ($groupRows === []) {
                continue;
            }

            $groups[] = [
                'name' => $name,
                'rows' => $groupRows,
                'average' => $this->familyAverage($groupRows),
            ];
        }

        $knownCodes = collect(self::SUBJECT_GROUPS)->flatten()->all();
        $otherRows = $rows
            ->reject(fn (array $row): bool => in_array($row['subject']->code, $knownCodes, true))
            ->values()
            ->all();

        if ($otherRows !== []) {
            $groups[] = [
                'name' => 'Autres matières',
                'rows' => $otherRows,
                'average' => $this->familyAverage($otherRows),
            ];
        }

        return $groups;
    }

    /** @param iterable<BulletinSubjectRow> $rows */
    private function familyAverage(iterable $rows): ?float
    {
        return $this->gradeCalculationService->familyAverage(
            collect($rows)->map(fn (array $row): array => [
                'general' => $row['average'],
                'coefficient' => $row['coefficient'],
                'points' => $row['points'],
            ]),
        );
    }

    /** @return list<array{position: int, average: ?float}> */
    private function recalls(ReportCard $reportCard, int $termPosition): array
    {
        if ($termPosition <= 1) {
            return [];
        }

        $priorTerms = Term::query()
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->get()
            ->sortBy(fn (Term $term): array => [
                $term->starts_at?->format('Y-m-d') ?? '9999-12-31',
                $term->position ?? PHP_INT_MAX,
                $term->id,
            ])
            ->take($termPosition - 1)
            ->values();
        $cards = ReportCard::query()
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->where('school_class_id', $reportCard->school_class_id)
            ->where('student_id', $reportCard->student_id)
            ->whereIn('term_id', $priorTerms->pluck('id'))
            ->get()
            ->keyBy('term_id');

        return $priorTerms
            ->map(fn (Term $term, int $index): array => [
                'position' => $index + 1,
                'average' => $cards->get($term->id)?->general_average === null
                    ? null
                    : (float) $cards->get($term->id)->general_average,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{class_subject: ClassSubject, devoir: ?float, composition: ?float, general: ?float, coefficient: float, points: ?float}  $row
     * @return BulletinSubjectRow
     */
    private function subjectRow(array $row): array
    {
        $classSubject = $row['class_subject'];
        $subject = $classSubject->subject;

        if (! $subject instanceof Subject) {
            throw new LogicException('Une matière active doit exister pour chaque affectation de classe.');
        }

        return [
            'subject' => $subject,
            'coefficient' => $row['coefficient'],
            'devoir_average' => $row['devoir'],
            'composition_average' => $row['composition'],
            'average' => $row['general'],
            'points' => $row['points'],
            'appreciation' => $this->appreciation($row['general']),
            'teacher' => $classSubject->teacher_id ? $classSubject->teacher->name : '-',
        ];
    }

    /** @return array{average: ?float, highest: ?float, lowest: ?float} */
    private function classStats(ReportCard $reportCard): array
    {
        $averages = ReportCard::query()
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->where('term_id', $reportCard->term_id)
            ->where('school_class_id', $reportCard->school_class_id)
            ->whereNotNull('general_average')
            ->pluck('general_average')
            ->map(fn ($average): float => (float) $average);
        $extremes = $this->reportCardService->classExtremes($reportCard->term, $reportCard->schoolClass);

        return [
            'average' => $averages->isEmpty() ? null : round((float) $averages->avg(), 2),
            'highest' => $extremes['highest'],
            'lowest' => $extremes['lowest'],
        ];
    }

    private function appreciation(?float $average): string
    {
        if ($average === null) {
            return 'Non noté';
        }

        return match (true) {
            $average >= 16 => 'Excellent',
            $average >= 14 => 'Très bien',
            $average >= 12 => 'Bien',
            $average >= 10 => 'Passable',
            $average >= 8 => 'Insuffisant',
            default => 'Très insuffisant',
        };
    }
}
