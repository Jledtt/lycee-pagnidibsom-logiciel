<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Services\SchoolAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly SchoolAccessService $access) {}

    public function __invoke(Request $request): JsonResponse
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        if ($academicYear === null) {
            return response()->json([
                'message' => 'Aucune année scolaire active.',
            ], 422);
        }

        $data = [
            'academic_year' => $academicYear,
            'students_count' => $this->access
                ->scopeStudents(Student::query(), $request->user())
                ->where('status', 'active')
                ->count(),
        ];

        if ($request->user()->can('enrollments.view')) {
            $data['enrollments_count'] = Enrollment::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'active')
                ->count();
        }

        if ($request->user()->can('payments.view')) {
            $data['payments_total'] = Payment::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'valid')
                ->sum('amount');
        }

        if ($request->user()->can('attendance.view')) {
            $data['absences_today'] = AttendanceRecord::query()
                ->whereIn('status', ['absent', 'late'])
                ->whereHas('session', function ($query) use ($request): void {
                    $this->access
                        ->scopeAttendanceSessions($query, $request->user())
                        ->whereDate('session_date', today());
                })
                ->count();
        }

        return response()->json($data);
    }
}
