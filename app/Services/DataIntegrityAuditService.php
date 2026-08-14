<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Guardian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DataIntegrityAuditService
{
    /**
     * @return array{
     *     generated_at: string,
     *     connection: string,
     *     checks: array<int, array{key: string, label: string, severity: string, count: int, samples: array<int, string>}>,
     *     blocker_count: int,
     *     warning_count: int,
     *     safe_for_constraints: bool
     * }
     */
    public function run(): array
    {
        $checks = collect([
            ...$this->academicYearChecks(),
            ...$this->guardianChecks(),
            ...$this->timetableChecks(),
            ...$this->paymentChecks(),
        ]);

        $blockerCount = $checks
            ->where('severity', 'blocking')
            ->sum('count');
        $warningCount = $checks
            ->where('severity', 'warning')
            ->sum('count');

        return [
            'generated_at' => now()->toIso8601String(),
            'connection' => DB::connection()->getDriverName(),
            'checks' => $checks->values()->all(),
            'blocker_count' => (int) $blockerCount,
            'warning_count' => (int) $warningCount,
            'safe_for_constraints' => $blockerCount === 0,
        ];
    }

    /** @return array<int, array{key: string, label: string, severity: string, count: int, samples: array<int, string>}> */
    private function academicYearChecks(): array
    {
        $years = AcademicYear::query()
            ->orderBy('starts_at')
            ->get(['id', 'starts_at', 'ends_at', 'is_active', 'status']);
        $activeYears = $years->where('is_active', true);
        $statusMismatches = $years->filter(
            fn (AcademicYear $year): bool => $year->is_active !== ($year->status === 'active'),
        );
        $invalidDates = $years->filter(
            fn (AcademicYear $year): bool => $year->starts_at->greaterThanOrEqualTo($year->ends_at),
        );
        $overlaps = collect();

        for ($left = 0; $left < $years->count(); $left++) {
            for ($right = $left + 1; $right < $years->count(); $right++) {
                $first = $years[$left];
                $second = $years[$right];

                if ($first->starts_at->lessThanOrEqualTo($second->ends_at)
                    && $second->starts_at->lessThanOrEqualTo($first->ends_at)) {
                    $overlaps->push($first->id.'/'.$second->id);
                }
            }
        }

        return [
            $this->check(
                'academic_years.multiple_active',
                'Plusieurs années scolaires actives',
                'blocking',
                max($activeYears->count() - 1, 0),
                $activeYears->pluck('id')->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'academic_years.none_active',
                'Aucune année scolaire active',
                'warning',
                $years->isNotEmpty() && $activeYears->isEmpty() ? 1 : 0,
            ),
            $this->check(
                'academic_years.status_mismatch',
                'Statut incohérent avec le marqueur actif',
                'blocking',
                $statusMismatches->count(),
                $statusMismatches->pluck('id')->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'academic_years.invalid_dates',
                'Date de fin antérieure ou égale à la date de début',
                'blocking',
                $invalidDates->count(),
                $invalidDates->pluck('id')->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'academic_years.overlaps',
                'Périodes d’années scolaires qui se chevauchent',
                'blocking',
                $overlaps->count(),
                $overlaps,
            ),
        ];
    }

    /** @return array<int, array{key: string, label: string, severity: string, count: int, samples: array<int, string>}> */
    private function guardianChecks(): array
    {
        $duplicatePhones = Guardian::query()
            ->get(['id', 'phone_primary'])
            ->groupBy(fn (Guardian $guardian): string => $this->normalizePhone($guardian->phone_primary))
            ->filter(fn (Collection $guardians, string $phone): bool => $phone !== '' && $guardians->count() > 1);
        $multiplePrimary = DB::table('guardian_student')
            ->where('is_primary', true)
            ->select('student_id')
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('student_id');
        $duplicateRelationships = DB::table('guardian_student')
            ->whereIn('relationship', ['father', 'mother', 'tutor'])
            ->select('student_id', 'relationship')
            ->groupBy('student_id', 'relationship')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $withoutPrimary = DB::table('guardian_student')
            ->select('student_id')
            ->groupBy('student_id')
            ->havingRaw('SUM(CASE WHEN is_primary = 1 THEN 1 ELSE 0 END) = 0')
            ->pluck('student_id');

        return [
            $this->check(
                'guardians.duplicate_phone',
                'Téléphone principal partagé par plusieurs fiches responsables',
                'warning',
                $duplicatePhones->count(),
                $duplicatePhones->keys(),
            ),
            $this->check(
                'guardian_student.multiple_primary',
                'Plusieurs responsables principaux pour un élève',
                'blocking',
                $multiplePrimary->count(),
                $multiplePrimary->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'guardian_student.duplicate_relationship',
                'Plusieurs pères, mères ou tuteurs du même type',
                'blocking',
                $duplicateRelationships->count(),
                $duplicateRelationships->map(
                    fn (object $row): string => $row->student_id.'/'.$row->relationship,
                ),
            ),
            $this->check(
                'guardian_student.without_primary',
                'Élève avec responsable mais sans responsable principal',
                'warning',
                $withoutPrimary->count(),
                $withoutPrimary->map(fn ($id): string => (string) $id),
            ),
        ];
    }

    /** @return array<int, array{key: string, label: string, severity: string, count: int, samples: array<int, string>}> */
    private function timetableChecks(): array
    {
        $duplicateCells = DB::table('timetable_entries')
            ->select('timetable_id', 'day_of_week', 'sort_order')
            ->groupBy('timetable_id', 'day_of_week', 'sort_order')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $teacherConflicts = DB::table('timetable_entries')
            ->whereNotNull('teacher_id')
            ->whereNotNull('timetable_period_id')
            ->select('teacher_id', 'day_of_week', 'timetable_period_id')
            ->groupBy('teacher_id', 'day_of_week', 'timetable_period_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $invalidTimes = DB::table('timetable_entries')
            ->where('is_break', false)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereColumn('starts_at', '>=', 'ends_at')
            ->pluck('id');

        return [
            $this->check(
                'timetable_entries.duplicate_cell',
                'Plusieurs cours dans une même cellule de grille',
                'blocking',
                $duplicateCells->count(),
                $duplicateCells->map(
                    fn (object $row): string => $row->timetable_id.'/'.$row->day_of_week.'/'.$row->sort_order,
                ),
            ),
            $this->check(
                'timetable_entries.teacher_conflict',
                'Professeur affecté à plusieurs classes au même créneau',
                'blocking',
                $teacherConflicts->count(),
                $teacherConflicts->map(
                    fn (object $row): string => $row->teacher_id.'/'.$row->day_of_week.'/'.$row->timetable_period_id,
                ),
            ),
            $this->check(
                'timetable_entries.invalid_times',
                'Cours dont l’heure de fin ne suit pas l’heure de début',
                'blocking',
                $invalidTimes->count(),
                $invalidTimes->map(fn ($id): string => (string) $id),
            ),
        ];
    }

    /** @return array<int, array{key: string, label: string, severity: string, count: int, samples: array<int, string>}> */
    private function paymentChecks(): array
    {
        $nonPositivePayments = DB::table('payments')->where('amount', '<=', 0)->pluck('id');
        $nonPositiveLines = DB::table('payment_lines')->where('amount', '<=', 0)->pluck('id');
        $lineSumMismatches = DB::table('payments')
            ->leftJoin('payment_lines', 'payment_lines.payment_id', '=', 'payments.id')
            ->select('payments.id', 'payments.amount')
            ->groupBy('payments.id', 'payments.amount')
            ->havingRaw('COALESCE(SUM(payment_lines.amount), 0) <> payments.amount')
            ->pluck('payments.id');
        $withoutEnrollment = DB::table('payments')->whereNull('enrollment_id')->pluck('id');
        $enrollmentMismatches = DB::table('payments')
            ->join('enrollments', 'enrollments.id', '=', 'payments.enrollment_id')
            ->where(function ($query): void {
                $query->whereColumn('payments.student_id', '!=', 'enrollments.student_id')
                    ->orWhereColumn('payments.academic_year_id', '!=', 'enrollments.academic_year_id');
            })
            ->pluck('payments.id');
        $scheduleMismatches = DB::table('payment_lines')
            ->join('payments', 'payments.id', '=', 'payment_lines.payment_id')
            ->join('fee_schedules', 'fee_schedules.id', '=', 'payment_lines.fee_schedule_id')
            ->leftJoin('enrollments', 'enrollments.id', '=', 'payments.enrollment_id')
            ->where(function ($query): void {
                $query->whereColumn('payments.academic_year_id', '!=', 'fee_schedules.academic_year_id')
                    ->orWhereColumn('payment_lines.fee_type_id', '!=', 'fee_schedules.fee_type_id')
                    ->orWhere(function ($classQuery): void {
                        $classQuery->whereNotNull('payments.enrollment_id')
                            ->whereColumn('enrollments.school_class_id', '!=', 'fee_schedules.school_class_id');
                    });
            })
            ->pluck('payment_lines.id');
        $cancelStateMismatches = DB::table('payments')
            ->where(function ($query): void {
                $query->where(function ($cancelled): void {
                    $cancelled->where('status', 'cancelled')
                        ->where(function ($missing): void {
                            $missing->whereNull('cancelled_at')
                                ->orWhereNull('cancellation_reason');
                        });
                })->orWhere(function ($valid): void {
                    $valid->where('status', 'valid')
                        ->where(function ($unexpected): void {
                            $unexpected->whereNotNull('cancelled_at')
                                ->orWhereNotNull('cancelled_by')
                                ->orWhereNotNull('cancellation_reason');
                        });
                });
            })
            ->pluck('id');

        return [
            $this->check(
                'payments.non_positive',
                'Paiements dont le montant est nul ou négatif',
                'blocking',
                $nonPositivePayments->count(),
                $nonPositivePayments->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'payment_lines.non_positive',
                'Lignes de paiement dont le montant est nul ou négatif',
                'blocking',
                $nonPositiveLines->count(),
                $nonPositiveLines->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'payments.line_sum_mismatch',
                'Total du reçu différent de la somme de ses lignes',
                'blocking',
                $lineSumMismatches->count(),
                $lineSumMismatches->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'payments.without_enrollment',
                'Paiement sans inscription associée',
                'warning',
                $withoutEnrollment->count(),
                $withoutEnrollment->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'payments.enrollment_mismatch',
                'Paiement lié à une inscription d’un autre élève ou d’une autre année',
                'blocking',
                $enrollmentMismatches->count(),
                $enrollmentMismatches->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'payment_lines.schedule_mismatch',
                'Ligne liée à un échéancier d’une autre année, classe ou nature',
                'blocking',
                $scheduleMismatches->count(),
                $scheduleMismatches->map(fn ($id): string => (string) $id),
            ),
            $this->check(
                'payments.cancel_state_mismatch',
                'Statut d’annulation incohérent avec ses informations',
                'blocking',
                $cancelStateMismatches->count(),
                $cancelStateMismatches->map(fn ($id): string => (string) $id),
            ),
        ];
    }

    /**
     * @param  iterable<int|string, int|string>  $samples
     * @return array{key: string, label: string, severity: string, count: int, samples: array<int, string>}
     */
    private function check(
        string $key,
        string $label,
        string $severity,
        int $count,
        iterable $samples = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'severity' => $severity,
            'count' => $count,
            'samples' => collect($samples)
                ->take(10)
                ->map(fn ($sample): string => (string) $sample)
                ->values()
                ->all(),
        ];
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone ?? '') ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '226')) {
            return substr($digits, 3);
        }

        return $digits;
    }
}
