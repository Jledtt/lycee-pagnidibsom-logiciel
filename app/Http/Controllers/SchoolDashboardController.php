<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SchoolDashboardController extends Controller
{
    public function __invoke(): View
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        $activeClassQuery = SchoolClass::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active');
        $activeClassIds = (clone $activeClassQuery)->pluck('id');
        $activeEnrollments = $this->activeEnrollments($academicYear);
        $financialRows = $this->financialRows($academicYear, $activeEnrollments);
        $totalExpected = $financialRows->sum('expected');
        $totalPaid = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'valid')
            ->sum('amount');
        $todayPayments = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'valid')
            ->whereDate('paid_at', today());
        $todaySessions = AttendanceSession::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereDate('session_date', today())
            ->pluck('school_class_id')
            ->unique();

        $stats = [
            'students' => Student::query()->where('status', 'active')->count(),
            'classes' => $activeClassIds->count(),
            'enrollments' => $activeEnrollments->count(),
            'payments' => $totalPaid,
            'absences_today' => AttendanceRecord::query()
                ->whereIn('status', ['absent', 'late'])
                ->whereHas('session', fn ($query) => $query->whereDate('session_date', today()))
                ->count(),
        ];

        $classes = SchoolClass::query()
            ->withCount(['enrollments' => fn ($query) => $query->where('status', 'active')])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->orderBy('name')
            ->limit(8)
            ->get();

        $recentPayments = Payment::query()
            ->with('student')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'valid')
            ->latest('paid_at')
            ->limit(5)
            ->get();

        $classesWithoutTariffs = $this->classesWithoutTariffs($academicYear, $activeClassIds);

        return view('dashboard', [
            'academicAlerts' => [
                'assessments_week' => Assessment::query()
                    ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count(),
                'bulletins_generated' => ReportCard::query()
                    ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                    ->count(),
                'bulletins_pending' => ReportCard::query()
                    ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                    ->where('status', 'draft')
                    ->count(),
                'locked_assessments' => Assessment::query()
                    ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                    ->where('is_locked', true)
                    ->count(),
            ],
            'academicYear' => $academicYear,
            'attendanceAlerts' => [
                'absences_today' => AttendanceRecord::query()
                    ->where('status', 'absent')
                    ->whereHas('session', fn ($query) => $query->whereDate('session_date', today()))
                    ->count(),
                'late_today' => AttendanceRecord::query()
                    ->where('status', 'late')
                    ->whereHas('session', fn ($query) => $query->whereDate('session_date', today()))
                    ->count(),
                'classes_pointed' => $todaySessions->count(),
                'classes_not_pointed' => max($activeClassIds->count() - $todaySessions->count(), 0),
                'not_pointed_classes' => SchoolClass::query()
                    ->whereIn('id', $activeClassIds)
                    ->whereNotIn('id', $todaySessions)
                    ->orderBy('name')
                    ->limit(6)
                    ->get(),
            ],
            'stats' => $stats,
            'classes' => $classes,
            'configurationAlerts' => [
                'classes_without_tariffs_count' => $classesWithoutTariffs->count(),
                'classes_without_tariffs' => $classesWithoutTariffs,
                'classes_without_subjects' => SchoolClass::query()
                    ->whereIn('id', $activeClassIds)
                    ->whereDoesntHave('classSubjects', fn ($query) => $query->where('is_active', true))
                    ->orderBy('name')
                    ->limit(5)
                    ->get(),
            ],
            'financeAlerts' => [
                'expected' => $totalExpected,
                'paid' => (float) $totalPaid,
                'remaining' => max($totalExpected - (float) $totalPaid, 0),
                'today_paid' => (float) (clone $todayPayments)->sum('amount'),
                'today_count' => (clone $todayPayments)->count(),
                'unpaid_count' => $financialRows->filter(fn (array $row) => $row['balance'] > 0)->count(),
                'top_unpaid' => $financialRows
                    ->filter(fn (array $row) => $row['balance'] > 0)
                    ->sortByDesc('balance')
                    ->take(6)
                    ->values(),
            ],
            'recentPayments' => $recentPayments,
        ]);
    }

    private function activeEnrollments(?AcademicYear $academicYear): Collection
    {
        return Enrollment::query()
            ->with(['student', 'schoolClass'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->get();
    }

    private function financialRows(?AcademicYear $academicYear, Collection $enrollments): Collection
    {
        $tariffsByClass = FeeSchedule::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->selectRaw('school_class_id, SUM(amount) as expected')
            ->groupBy('school_class_id')
            ->pluck('expected', 'school_class_id');

        $paidByStudent = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'valid')
            ->selectRaw('student_id, SUM(amount) as paid')
            ->groupBy('student_id')
            ->pluck('paid', 'student_id');

        return $enrollments->map(function (Enrollment $enrollment) use ($tariffsByClass, $paidByStudent) {
            $expected = (float) ($tariffsByClass[$enrollment->school_class_id] ?? 0);
            $paid = (float) ($paidByStudent[$enrollment->student_id] ?? 0);

            return [
                'student' => $enrollment->student,
                'class' => $enrollment->schoolClass,
                'expected' => $expected,
                'paid' => $paid,
                'balance' => max($expected - $paid, 0),
            ];
        });
    }

    private function classesWithoutTariffs(?AcademicYear $academicYear, Collection $activeClassIds): Collection
    {
        $classIdsWithTariffs = FeeSchedule::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereIn('school_class_id', $activeClassIds)
            ->pluck('school_class_id')
            ->unique();

        return SchoolClass::query()
            ->whereIn('id', $activeClassIds)
            ->whereNotIn('id', $classIdsWithTariffs)
            ->orderBy('name')
            ->limit(5)
            ->get();
    }
}
