<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        if ($academicYear === null) {
            return response()->json([
                'message' => 'Aucune année scolaire active.',
            ], 422);
        }

        return response()->json([
            'academic_year' => $academicYear,
            'students_count' => Student::query()->where('status', 'active')->count(),
            'enrollments_count' => Enrollment::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'active')
                ->count(),
            'payments_total' => Payment::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'valid')
                ->sum('amount'),
            'absences_today' => AttendanceRecord::query()
                ->whereIn('status', ['absent', 'late'])
                ->whereHas('session', fn ($query) => $query->whereDate('session_date', today()))
                ->count(),
        ]);
    }
}
