<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\View\View;

class SchoolDashboardController extends Controller
{
    public function __invoke(): View
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        $stats = [
            'students' => Student::query()->where('status', 'active')->count(),
            'classes' => SchoolClass::query()
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->where('status', 'active')
                ->count(),
            'enrollments' => Enrollment::query()
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->where('status', 'active')
                ->count(),
            'payments' => Payment::query()
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->where('status', 'valid')
                ->sum('amount'),
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
            ->where('status', 'valid')
            ->latest('paid_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'academicYear' => $academicYear,
            'stats' => $stats,
            'classes' => $classes,
            'recentPayments' => $recentPayments,
        ]);
    }
}
