<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\MockExam;
use App\Models\MockExamCandidate;
use App\Models\MockExamSubject;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExportCenterService
{
    public function __construct(private PaymentFinancialProfileService $financialProfiles)
    {
    }

    public function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('is_active', true)
            ->first()
            ?? AcademicYear::query()->orderByDesc('id')->first();
    }

    public function classesFor(AcademicYear $academicYear): Collection
    {
        return SchoolClass::query()
            ->with('level')
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('name')
            ->get();
    }

    public function termsFor(AcademicYear $academicYear): Collection
    {
        return Term::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function periodsFor(AcademicYear $academicYear): Collection
    {
        return TermPeriod::query()
            ->with('term')
            ->whereHas('term', fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function subjects(): Collection
    {
        return Subject::query()
            ->orderBy('name')
            ->get();
    }

    public function mockExamsFor(AcademicYear $academicYear): Collection
    {
        return MockExam::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderByDesc('id')
            ->get();
    }

    public function studentRows(AcademicYear $academicYear, ?int $classId = null, ?string $status = null): Collection
    {
        return Enrollment::query()
            ->with(['student.guardians', 'schoolClass'])
            ->where('academic_year_id', $academicYear->id)
            ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
            ->whereHas('student', function ($query) use ($status) {
                $query->when($status, fn ($subQuery) => $subQuery->where('status', $status));
            })
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->select('enrollments.*')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->get()
            ->map(function (Enrollment $enrollment) {
                $student = $enrollment->student;
                $guardian = $student?->guardians->first();

                return [
                    $student?->matricule,
                    $student?->last_name,
                    $student?->first_name,
                    $student?->gender_label ?? $student?->gender,
                    $this->dateValue($student?->birth_date),
                    $student?->birth_place,
                    $enrollment->schoolClass?->name,
                    $student?->home_phone,
                    $guardian?->full_name,
                    $guardian?->phone_primary,
                    $student?->status,
                ];
            });
    }

    public function paymentRows(
        AcademicYear $academicYear,
        ?int $classId = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Collection {
        return Payment::query()
            ->with(['student', 'enrollment.schoolClass', 'receiver'])
            ->where('academic_year_id', $academicYear->id)
            ->when($classId, fn ($query) => $query->whereHas('enrollment', fn ($subQuery) => $subQuery->where('school_class_id', $classId)))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($dateFrom, fn ($query) => $query->whereDate('paid_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('paid_at', '<=', $dateTo))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Payment $payment) => [
                $payment->receipt_number,
                $this->dateTimeValue($payment->paid_at),
                $this->studentName($payment->student),
                $payment->student?->matricule,
                $payment->enrollment?->schoolClass?->name,
                (float) $payment->amount,
                $payment->payment_method,
                $payment->status,
                $payment->receiver?->name ?? $payment->receiver?->username,
                $payment->notes,
            ]);
    }

    public function unpaidRows(AcademicYear $academicYear, ?int $classId = null, ?float $minimumBalance = null): Collection
    {
        return $this->financialProfiles
            ->unpaidRows($academicYear)
            ->filter(function (array $row) use ($classId, $minimumBalance) {
                $enrollment = $row['enrollment'];
                $balance = $row['summary']['balance'];

                if ($classId && (int) $enrollment->school_class_id !== (int) $classId) {
                    return false;
                }

                if (! is_null($minimumBalance) && ! is_null($balance) && $balance < $minimumBalance) {
                    return false;
                }

                return true;
            })
            ->map(function (array $row) {
                $enrollment = $row['enrollment'];
                $student = $enrollment->student;
                $guardian = $student?->guardians->first();
                $summary = $row['summary'];

                return [
                    $this->studentName($student),
                    $student?->matricule,
                    $enrollment->schoolClass?->name,
                    $summary['expected'],
                    $summary['paid'],
                    $summary['balance'],
                    $guardian?->full_name,
                    $guardian?->phone_primary,
                ];
            })
            ->values();
    }

    public function gradeRows(
        AcademicYear $academicYear,
        ?int $classId = null,
        ?int $termId = null,
        ?int $periodId = null,
        ?int $subjectId = null
    ): Collection {
        return Grade::query()
            ->with(['student', 'assessment.term', 'assessment.termPeriod', 'assessment.subject', 'assessment.schoolClass'])
            ->whereHas('assessment', function ($query) use ($academicYear, $classId, $termId, $periodId, $subjectId) {
                $query->where('academic_year_id', $academicYear->id)
                    ->when($classId, fn ($subQuery) => $subQuery->where('school_class_id', $classId))
                    ->when($termId, fn ($subQuery) => $subQuery->where('term_id', $termId))
                    ->when($periodId, fn ($subQuery) => $subQuery->where('term_period_id', $periodId))
                    ->when($subjectId, fn ($subQuery) => $subQuery->where('subject_id', $subjectId));
            })
            ->join('students', 'students.id', '=', 'grades.student_id')
            ->select('grades.*')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->get()
            ->map(function (Grade $grade) {
                $assessment = $grade->assessment;

                return [
                    $assessment?->schoolClass?->name,
                    $assessment?->termPeriod?->name ?? $assessment?->term?->name,
                    $assessment?->title,
                    $assessment?->subject?->name,
                    $grade->student?->matricule,
                    $this->studentName($grade->student),
                    $grade->is_absent ? 'Absent' : $grade->score,
                    $assessment?->max_score,
                    $grade->resolvedStatus(),
                    $grade->comment,
                ];
            });
    }

    public function attendanceRows(
        AcademicYear $academicYear,
        ?int $classId = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Collection {
        return AttendanceRecord::query()
            ->with(['student', 'session.schoolClass'])
            ->whereHas('session', function ($query) use ($academicYear, $classId, $dateFrom, $dateTo) {
                $query->where('academic_year_id', $academicYear->id)
                    ->when($classId, fn ($subQuery) => $subQuery->where('school_class_id', $classId))
                    ->when($dateFrom, fn ($subQuery) => $subQuery->whereDate('session_date', '>=', $dateFrom))
                    ->when($dateTo, fn ($subQuery) => $subQuery->whereDate('session_date', '<=', $dateTo));
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->select('attendance_records.*')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->get()
            ->map(fn (AttendanceRecord $record) => [
                $this->dateValue($record->session?->session_date),
                $record->session?->schoolClass?->name,
                $record->student?->matricule,
                $this->studentName($record->student),
                $record->status,
                $record->minutes_late,
                $record->reason,
                $this->dateTimeValue($record->justified_at),
            ]);
    }

    public function mockExamResultRows(AcademicYear $academicYear, ?int $mockExamId = null, ?int $classId = null, ?string $status = null): Collection
    {
        return MockExamCandidate::query()
            ->with(['student', 'schoolClass', 'mockExam', 'scores.subject.subject'])
            ->whereHas('mockExam', fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($mockExamId, fn ($query) => $query->where('mock_exam_id', $mockExamId))
            ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->join('students', 'students.id', '=', 'mock_exam_candidates.student_id')
            ->select('mock_exam_candidates.*')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->get()
            ->map(fn (MockExamCandidate $candidate) => [
                $candidate->mockExam?->name,
                $candidate->schoolClass?->name,
                $candidate->anonymous_code,
                $candidate->student?->matricule,
                $this->studentName($candidate->student),
                $candidate->room_name,
                $this->mockExamAverage($candidate),
                $candidate->status,
                $candidate->jury_decision,
                $candidate->jury_observation,
            ]);
    }

    public function teacherFeeRows(AcademicYear $academicYear, ?int $mockExamId = null, ?string $feeStatus = null): Collection
    {
        return MockExamSubject::query()
            ->with(['mockExam', 'subject'])
            ->whereHas('mockExam', fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($mockExamId, fn ($query) => $query->where('mock_exam_id', $mockExamId))
            ->when($feeStatus, fn ($query) => $query->where('fee_status', $feeStatus))
            ->orderBy('correction_teacher_name')
            ->orderBy('exam_date')
            ->get()
            ->map(fn (MockExamSubject $examSubject) => [
                $examSubject->mockExam?->name,
                $examSubject->subject?->name,
                $examSubject->exam_part_label,
                $examSubject->correction_teacher_name,
                $examSubject->fee_rate,
                $examSubject->fee_amount,
                $examSubject->fee_status,
                $this->dateValue($examSubject->fee_paid_at),
                $examSubject->fee_payment_reference,
            ]);
    }

    public function filename(string $prefix, AcademicYear $academicYear, ?SchoolClass $schoolClass = null): string
    {
        $parts = [$prefix, $academicYear->name];

        if ($schoolClass) {
            $parts[] = $schoolClass->name;
        }

        return Str::slug(implode('-', $parts), '-') . '.xlsx';
    }

    private function studentName(?Student $student): string
    {
        return trim(($student?->last_name ?? '') . ' ' . ($student?->first_name ?? ''));
    }

    private function mockExamAverage(MockExamCandidate $candidate): ?float
    {
        $weighted = 0.0;
        $coefficients = 0.0;

        foreach ($candidate->scores as $score) {
            if ($score->is_absent || is_null($score->score)) {
                continue;
            }

            $coefficient = (float) ($score->subject?->coefficient ?: 1);
            $weighted += (float) $score->score * $coefficient;
            $coefficients += $coefficient;
        }

        return $coefficients > 0 ? round($weighted / $coefficients, 2) : null;
    }

    private function dateValue(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return method_exists($date, 'format') ? $date->format('d/m/Y') : (string) $date;
    }

    private function dateTimeValue(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return method_exists($date, 'format') ? $date->format('d/m/Y H:i') : (string) $date;
    }
}
