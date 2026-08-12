<?php

namespace App\Services;

use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Support\Collection;

class ReportCardService
{
    public function __construct(
        private readonly GradeCalculationService $gradeCalculationService,
        private readonly CompetitionRankingService $competitionRankingService,
    ) {}

    public function generateForClass(SchoolClass $schoolClass, Term $term): array
    {
        $rows = $this->previewForClass($schoolClass, $term);

        foreach ($rows as $row) {
            $reportCard = ReportCard::query()->firstOrNew([
                'academic_year_id' => $schoolClass->academic_year_id,
                'term_id' => $term->id,
                'student_id' => $row['student']->id,
            ]);

            $reportCard->fill([
                'school_class_id' => $schoolClass->id,
                'general_average' => $row['average'],
                'rank' => $row['rank'],
                'rank_is_tied' => $row['rank_is_tied'],
                'class_size' => count($rows),
                'appreciation' => $this->appreciationForAverage($row['average']),
                'decision' => $reportCard->decision ?: $this->decisionForAverage($row['average']),
                'status' => $reportCard->exists ? $reportCard->status : 'draft',
            ])->save();
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
     * @return Collection<int, array<string, mixed>>
     */
    public function annualSummariesForClass(SchoolClass $schoolClass): Collection
    {
        $terms = $this->orderedTerms($schoolClass->academic_year_id);
        $cardsByStudent = ReportCard::query()
            ->where('academic_year_id', $schoolClass->academic_year_id)
            ->where('school_class_id', $schoolClass->id)
            ->whereIn('term_id', $terms->pluck('id'))
            ->get()
            ->groupBy('student_id');
        $students = $schoolClass->enrollments()
            ->with('student')
            ->where('status', 'active')
            ->whereHas('student', fn ($query) => $query->where('students.status', 'active'))
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
        $lastTermId = $terms->last()?->id;

        $rows = $students->map(function ($student) use ($cardsByStudent, $lastTermId, $terms): array {
            $cards = $cardsByStudent->get($student->id, collect())->keyBy('term_id');
            $termAverages = $terms->map(fn (Term $term): array => [
                'name' => $term->name,
                'position' => $this->termPosition($term),
                'average' => $cards->get($term->id)?->general_average === null
                    ? null
                    : (float) $cards->get($term->id)->general_average,
            ]);

            return [
                'student_id' => $student->id,
                'term_averages' => $termAverages,
                'annual_average' => $this->annualAverage($termAverages->pluck('average')),
                'terms_count' => $termAverages->whereNotNull('average')->count(),
                'decision' => $lastTermId ? $cards->get($lastTermId)?->decision : null,
            ];
        })->values()->all();

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

        return $rankedRows
            ->map(function (array $row) use ($classAverage, $highestAverage, $rankedCount): array {
                $row['annual_class_average'] = $classAverage;
                $row['highest_annual_average'] = $highestAverage;
                $row['class_size'] = $rankedCount;

                return $row;
            })
            ->keyBy('student_id');
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
