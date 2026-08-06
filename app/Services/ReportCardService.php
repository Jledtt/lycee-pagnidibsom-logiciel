<?php

namespace App\Services;

use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Term;

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
}
