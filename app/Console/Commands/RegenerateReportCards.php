<?php

namespace App\Console\Commands;

use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\ReportCardService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RegenerateReportCards extends Command
{
    protected $signature = 'lpp:regenerate-report-cards
        {--class= : Identifiant, code ou nom de la classe}
        {--term= : Identifiant ou nom du trimestre}
        {--dry-run : Afficher les écarts sans modifier les bulletins}';

    protected $description = 'Prévisualise puis régénère les moyennes des bulletins existants';

    public function handle(ReportCardService $reportCardService): int
    {
        $classes = $this->classes()->get();

        if ($classes->isEmpty()) {
            $this->error('Aucune classe ne correspond aux filtres.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $comparisons = [];
        $pairs = [];

        foreach ($classes as $schoolClass) {
            $terms = $this->termsFor($schoolClass)->get();

            foreach ($terms as $term) {
                $rows = $reportCardService->previewForClass($schoolClass, $term);

                if ($rows === []) {
                    continue;
                }

                $existing = ReportCard::query()
                    ->where('academic_year_id', $schoolClass->academic_year_id)
                    ->where('school_class_id', $schoolClass->id)
                    ->where('term_id', $term->id)
                    ->get()
                    ->keyBy('student_id');

                foreach ($rows as $row) {
                    $oldAverage = $existing->get($row['student']->id)?->general_average;
                    $newAverage = $row['average'];

                    $comparisons[] = [
                        $schoolClass->name,
                        $term->name,
                        $row['student']->last_name.' '.$row['student']->first_name,
                        $this->formatAverage($oldAverage === null ? null : (float) $oldAverage),
                        $this->formatAverage($newAverage),
                        $this->formatDifference($oldAverage === null ? null : (float) $oldAverage, $newAverage),
                    ];
                }

                $pairs[] = [$schoolClass, $term];
            }
        }

        if ($comparisons === []) {
            $this->warn('Aucun élève actif ne correspond aux filtres.');

            return self::SUCCESS;
        }

        $this->table(
            ['Classe', 'Trimestre', 'Élève', 'Ancienne moyenne', 'Nouvelle moyenne', 'Écart'],
            $comparisons,
        );

        if ($dryRun) {
            $this->info('Simulation terminée : aucun bulletin n’a été modifié.');

            return self::SUCCESS;
        }

        foreach ($pairs as [$schoolClass, $term]) {
            $reportCardService->generateForClass($schoolClass, $term);
        }

        $this->info(count($pairs).' ensemble(s) classe/trimestre régénéré(s).');

        return self::SUCCESS;
    }

    /** @return Builder<SchoolClass> */
    private function classes(): Builder
    {
        $filter = trim((string) $this->option('class'));

        return SchoolClass::query()
            ->when(
                $filter !== '',
                fn (Builder $query) => $query->where(function (Builder $query) use ($filter): void {
                    $query->whereKey(is_numeric($filter) ? (int) $filter : 0)
                        ->orWhere('code', $filter)
                        ->orWhere('name', $filter);
                }),
                fn (Builder $query) => $query
                    ->where('status', 'active')
                    ->whereHas('academicYear', fn (Builder $query) => $query->where('is_active', true)),
            )
            ->orderBy('name');
    }

    /** @return Builder<Term> */
    private function termsFor(SchoolClass $schoolClass): Builder
    {
        $filter = trim((string) $this->option('term'));

        return Term::query()
            ->where('academic_year_id', $schoolClass->academic_year_id)
            ->when($filter !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filter): void {
                $query->whereKey(is_numeric($filter) ? (int) $filter : 0)
                    ->orWhere('name', $filter);
            }))
            ->orderBy('position');
    }

    private function formatAverage(?float $average): string
    {
        return $average === null ? '-' : number_format($average, 2, ',', ' ');
    }

    private function formatDifference(?float $oldAverage, ?float $newAverage): string
    {
        if ($oldAverage === null || $newAverage === null) {
            return '-';
        }

        return sprintf('%+.2f', $newAverage - $oldAverage);
    }
}
