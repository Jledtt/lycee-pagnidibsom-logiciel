<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @phpstan-type AnnualTermAverage array{name: string, position: int, average: ?float}
 * @phpstan-type AnnualInputRow array{
 *     student_id: int,
 *     term_averages: array<int, AnnualTermAverage>,
 *     annual_average: ?float,
 *     terms_count: int,
 *     decision: ?string
 * }
 * @phpstan-type AnnualSummary array{
 *     student_id: int,
 *     term_averages: array<int, AnnualTermAverage>,
 *     annual_average: ?float,
 *     terms_count: int,
 *     decision: ?string,
 *     annual_rank: ?int,
 *     annual_rank_is_tied: bool,
 *     annual_rank_label: ?string,
 *     annual_class_average: ?float,
 *     highest_annual_average: ?float,
 *     class_size: int
 * }
 */
class ReportCardService
{
    public function __construct(
        private readonly GradeCalculationService $gradeCalculationService,
        private readonly CompetitionRankingService $competitionRankingService,
    ) {}

    public function generateForClass(SchoolClass $schoolClass, Term $term): array
    {
        $rows = $this->previewForClass($schoolClass, $term);
        $rankedCount = collect($rows)->whereNotNull('average')->count();
        $unrankedCount = count($rows) - $rankedCount;

        foreach ($rows as $row) {
            $reportCard = ReportCard::query()->firstOrNew([
                'academic_year_id' => $schoolClass->academic_year_id,
                'term_id' => $term->id,
                'student_id' => $row['student']->id,
            ]);

            $generatedValues = [
                'school_class_id' => $schoolClass->id,
                'general_average' => $row['average'],
                'rank' => $row['rank'],
                'rank_is_tied' => $row['rank_is_tied'],
                'class_size' => count($rows),
                'class_size_ranked' => $rankedCount,
                'class_size_unranked' => $unrankedCount,
                'appreciation' => $this->appreciationForAverage($row['average']),
                'decision' => $reportCard->decision ?: $this->decisionForAverage($row['average']),
                'absence_hours' => $this->absenceHoursFor($row['student']->id, $term),
                'status' => $reportCard->exists ? $reportCard->status : 'draft',
            ];

            if (! $reportCard->exists) {
                $generatedValues['conduct'] = 'Bonne';
                $generatedValues['distinction'] = $this->suggestedDistinction($row['average']);
            }

            $reportCard->fill($generatedValues)->save();
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewForClass(SchoolClass $schoolClass, Term $term): array
    {
        $enrollments = $schoolClass->enrollments()
            ->with('student')
            ->where('status', 'active')
            ->get();

        $rows = [];

        foreach ($enrollments as $enrollment) {
            $average = $this->gradeCalculationService->generalAverage(
                $enrollment->student,
                $schoolClass,
                $term
            );

            $rows[] = [
                'student' => $enrollment->student,
                'average' => $average,
            ];
        }

        return $this->competitionRankingService->rank($rows);
    }

    public function absenceHoursFor(int $studentId, Term $term): float
    {
        if ($term->starts_at === null || $term->ends_at === null) {
            return 0.0;
        }

        // 1 enregistrement = 1 heure, à affiner si la durée de session est ajoutée.
        return (float) AttendanceRecord::query()
            ->where('student_id', $studentId)
            ->where('status', 'absent')
            ->whereNull('justified_at')
            ->whereHas('session', fn ($query) => $query->whereBetween('session_date', [
                $term->starts_at->toDateString(),
                $term->ends_at->toDateString(),
            ]))
            ->count();
    }

    public function suggestedDistinction(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        return match (true) {
            $average >= 16 => ReportCard::DISTINCTION_HIGH_HONORS_CONGRATULATIONS,
            $average >= 14 => ReportCard::DISTINCTION_HIGH_HONORS_ENCOURAGEMENT,
            $average >= 12 => ReportCard::DISTINCTION_HONOR_ROLL,
            default => null,
        };
    }

    /**
     * Sémantique confirmée : extrêmes du trimestre courant de la classe.
     *
     * @return array{highest: ?float, lowest: ?float}
     */
    public function classExtremes(Term $term, SchoolClass $schoolClass): array
    {
        $averages = ReportCard::query()
            ->where('academic_year_id', $schoolClass->academic_year_id)
            ->where('term_id', $term->id)
            ->where('school_class_id', $schoolClass->id)
            ->whereNotNull('general_average')
            ->pluck('general_average')
            ->map(fn ($average): float => (float) $average);

        return [
            'highest' => $averages->isEmpty() ? null : round((float) $averages->max(), 2),
            'lowest' => $averages->isEmpty() ? null : round((float) $averages->min(), 2),
        ];
    }

    public function termPosition(Term $term): int
    {
        $terms = $this->orderedTerms($term->academic_year_id);
        $index = $terms->search(fn (Term $candidate): bool => (int) $candidate->id === (int) $term->id);

        return $index === false ? 1 : $index + 1;
    }

    /**
     * @param  iterable<float|int|string|null>  $averages
     */
    public function annualAverage(iterable $averages): ?float
    {
        $knownAverages = collect($averages)
            ->filter(fn ($average): bool => $average !== null)
            ->map(fn ($average): float => (float) $average);

        return $knownAverages->isEmpty() ? null : round((float) $knownAverages->avg(), 2);
    }

    /**
     * @return array<int, AnnualSummary>
     */
    public function annualSummariesForClass(SchoolClass $schoolClass): array
    {
        $terms = $this->orderedTerms($schoolClass->academic_year_id);
        $cardsByStudent = ReportCard::query()
            ->where('academic_year_id', $schoolClass->academic_year_id)
            ->where('school_class_id', $schoolClass->id)
            ->whereIn('term_id', $terms->pluck('id'))
            ->get()
            ->groupBy('student_id');
        $students = Student::query()
            ->where('status', 'active')
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $schoolClass->academic_year_id)
                ->where('school_class_id', $schoolClass->id)
                ->where('enrollments.status', 'active'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $lastTermId = $terms->last()?->id;

        $rows = [];

        foreach ($students as $student) {
            $cards = $cardsByStudent->get($student->id, collect())->keyBy('term_id');
            $rows[] = $this->annualInputRow($student, $cards, $terms, $lastTermId);
        }

        $rankedRows = collect($this->competitionRankingService->rank(
            $rows,
            'annual_average',
            'annual_rank',
            'annual_rank_is_tied',
            'annual_rank_label',
        ));
        $annualAverages = $rankedRows->pluck('annual_average')->whereNotNull();
        $rankedCount = $annualAverages->count();
        $classAverage = $this->annualAverage($annualAverages);
        $highestAverage = $annualAverages->isEmpty() ? null : round((float) $annualAverages->max(), 2);

        $summaries = [];

        foreach ($rankedRows as $row) {
            $summary = $this->annualSummary(
                $row,
                $classAverage,
                $highestAverage,
                $rankedCount,
            );
            $summaries[$summary['student_id']] = $summary;
        }

        return $summaries;
    }

    /**
     * @param  Collection<int|string, ReportCard>  $cards
     * @param  Collection<int, Term>  $terms
     * @return AnnualInputRow
     */
    private function annualInputRow(Student $student, Collection $cards, Collection $terms, ?int $lastTermId): array
    {
        $termAverages = $terms
            ->mapWithKeys(fn (Term $term): array => [
                $term->id => $this->annualTermRow($term, $cards),
            ])
            ->all();
        $decision = $lastTermId ? $cards->get($lastTermId)?->decision : null;

        return [
            'student_id' => $student->id,
            'term_averages' => $termAverages,
            'annual_average' => $this->annualAverage(array_column($termAverages, 'average')),
            'terms_count' => collect($termAverages)->whereNotNull('average')->count(),
            'decision' => is_string($decision) ? $decision : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return AnnualSummary
     */
    private function annualSummary(array $row, ?float $classAverage, ?float $highestAverage, int $classSize): array
    {
        $studentId = $row['student_id'] ?? null;
        $termAverages = $row['term_averages'] ?? null;

        if (! is_int($studentId) || ! is_array($termAverages)) {
            throw new LogicException('La synthèse annuelle calculée est incomplète.');
        }

        return [
            'student_id' => $studentId,
            'term_averages' => $termAverages,
            'annual_average' => is_numeric($row['annual_average'] ?? null) ? (float) $row['annual_average'] : null,
            'terms_count' => is_int($row['terms_count'] ?? null) ? $row['terms_count'] : 0,
            'decision' => is_string($row['decision'] ?? null) ? $row['decision'] : null,
            'annual_rank' => is_int($row['annual_rank'] ?? null) ? $row['annual_rank'] : null,
            'annual_rank_is_tied' => ($row['annual_rank_is_tied'] ?? false) === true,
            'annual_rank_label' => is_string($row['annual_rank_label'] ?? null) ? $row['annual_rank_label'] : null,
            'annual_class_average' => $classAverage,
            'highest_annual_average' => $highestAverage,
            'class_size' => $classSize,
        ];
    }

    /**
     * @param  Collection<int|string, ReportCard>  $cards
     * @return array{name: string, position: int, average: ?float}
     */
    private function annualTermRow(Term $term, Collection $cards): array
    {
        $average = $cards->get($term->id)?->general_average;

        return [
            'name' => $term->name,
            'position' => $this->termPosition($term),
            'average' => $average === null ? null : (float) $average,
        ];
    }

    public function appreciationForAverage(?float $average): string
    {
        if ($average === null) {
            return 'Non note';
        }

        return match (true) {
            $average >= 16 => 'Tres bien',
            $average >= 14 => 'Bien',
            $average >= 12 => 'Assez bien',
            $average >= 10 => 'Passable',
            $average >= 8 => 'Insuffisant',
            default => 'Tres insuffisant',
        };
    }

    public function decisionForAverage(?float $average): string
    {
        if ($average === null) {
            return 'À compléter';
        }

        return $average >= 10 ? 'Admis' : 'A deliberer';
    }

    /**
     * @return Collection<int, Term>
     */
    private function orderedTerms(int $academicYearId): Collection
    {
        return Term::query()
            ->where('academic_year_id', $academicYearId)
            ->get()
            ->sortBy(fn (Term $term): array => [
                $term->starts_at?->format('Y-m-d') ?? '9999-12-31',
                $term->position ?? PHP_INT_MAX,
                $term->id,
            ])
            ->values();
    }
}
