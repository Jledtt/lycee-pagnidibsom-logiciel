<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\TeacherFeeStatement;
use App\Models\TeacherWorkSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherFeeService
{
    public function create(
        AcademicYear $academicYear,
        User $teacher,
        Carbon $periodMonth,
        array $sessionIds,
        array $rates,
        array $deductions,
        User $actor,
    ): TeacherFeeStatement {
        return DB::transaction(function () use (
            $academicYear,
            $teacher,
            $periodMonth,
            $sessionIds,
            $rates,
            $deductions,
            $actor,
        ): TeacherFeeStatement {
            $sessions = TeacherWorkSession::query()
                ->with(['schoolClass', 'subject'])
                ->whereIn('id', $sessionIds)
                ->where('academic_year_id', $academicYear->id)
                ->where('teacher_id', $teacher->id)
                ->where('status', 'validated')
                ->whereBetween('session_date', [$periodMonth->copy()->startOfMonth(), $periodMonth->copy()->endOfMonth()])
                ->whereDoesntHave('feeLine')
                ->lockForUpdate()
                ->get();

            if ($sessions->count() !== count(array_unique($sessionIds))) {
                throw ValidationException::withMessages([
                    'session_ids' => 'Certaines heures sont invalides, déjà payées ou hors de la période choisie.',
                ]);
            }

            if ($sessions->isEmpty()) {
                throw ValidationException::withMessages([
                    'session_ids' => 'Sélectionne au moins une heure validée.',
                ]);
            }

            $profile = $teacher->teacherProfile;
            $grossAmount = 0.0;
            $linePayloads = [];

            foreach ($sessions as $session) {
                $rate = (float) ($rates[$session->id] ?? $session->hourly_rate ?? $profile?->default_hourly_rate ?? 0);

                if ($rate <= 0) {
                    throw ValidationException::withMessages([
                        "rates.{$session->id}" => 'Le taux horaire doit être supérieur à zéro.',
                    ]);
                }

                $amount = round((float) $session->hours_worked * $rate, 2);
                $grossAmount += $amount;
                $linePayloads[] = [
                    'teacher_work_session_id' => $session->id,
                    'school_class_id' => $session->school_class_id,
                    'subject_id' => $session->subject_id,
                    'description' => trim(($session->subject?->name ?? 'Cours').' - '.($session->schoolClass?->name ?? 'Classe non précisée')),
                    'hours' => $session->hours_worked,
                    'hourly_rate' => $rate,
                    'amount' => $amount,
                ];
            }

            $taxRate = (float) ($deductions['withholding_tax_rate'] ?? $profile?->withholding_tax_rate ?? 2);
            $taxAmount = round($grossAmount * $taxRate / 100, 2);
            $advanceAmount = (float) ($deductions['advance_amount'] ?? 0);
            $otherDeductionAmount = (float) ($deductions['other_deduction_amount'] ?? 0);
            $netAmount = round($grossAmount - $taxAmount - $advanceAmount - $otherDeductionAmount, 2);

            if ($netAmount < 0) {
                throw ValidationException::withMessages([
                    'advance_amount' => 'Les retenues ne peuvent pas dépasser le montant brut.',
                ]);
            }

            $statement = TeacherFeeStatement::query()->create([
                'reference' => $this->nextReference($periodMonth),
                'academic_year_id' => $academicYear->id,
                'teacher_id' => $teacher->id,
                'period_month' => $periodMonth->copy()->startOfMonth(),
                'beneficiary_name' => $teacher->name,
                'identity_document_type' => $profile?->identity_document_type,
                'identity_document_number' => $profile?->identity_document_number,
                'gross_amount' => $grossAmount,
                'withholding_tax_rate' => $taxRate,
                'withholding_tax_amount' => $taxAmount,
                'advance_amount' => $advanceAmount,
                'other_deduction_amount' => $otherDeductionAmount,
                'net_amount' => $netAmount,
                'status' => 'draft',
                'payment_method' => $profile?->payment_method,
                'payment_reference' => $profile?->payment_reference,
                'notes' => $deductions['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $statement->lines()->createMany($linePayloads);

            return $statement->load(['teacher.teacherProfile', 'lines.schoolClass', 'lines.subject']);
        });
    }

    private function nextReference(Carbon $periodMonth): string
    {
        $prefix = 'HON-'.$periodMonth->format('Ym').'-';
        $lastReference = TeacherFeeStatement::query()
            ->where('reference', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');
        $nextNumber = $lastReference ? ((int) substr($lastReference, -4)) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
