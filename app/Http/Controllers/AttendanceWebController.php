<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $date = $request->date('date') ?? today();
        $schoolClass = $this->selectedClass($request, $classes);
        $sessions = $this->sessionsForDate($academicYear, $date);
        $selectedSession = $schoolClass
            ? $sessions->firstWhere('school_class_id', $schoolClass->id)
            : null;

        return view('attendance.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'date' => $date,
            'filters' => $request->only(['school_class_id', 'date']),
            'recentRecords' => $this->recentRecords($academicYear),
            'schoolClass' => $schoolClass,
            'selectedSession' => $selectedSession,
            'sessions' => $sessions,
            'summary' => $this->dailySummary($sessions),
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'session_date' => ['required', 'date'],
        ]);

        $session = AttendanceSession::query()->firstOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $data['school_class_id'],
                'session_date' => $data['session_date'],
            ],
            [
                'created_by' => $request->user()->id,
            ],
        );

        return redirect()
            ->route('attendance.sessions.edit', $session)
            ->with('success', 'Seance d appel prete.');
    }

    public function editSession(AttendanceSession $attendanceSession): View
    {
        $attendanceSession->load([
            'academicYear',
            'schoolClass.level',
            'records.student',
        ]);

        $students = $this->studentsForClass($attendanceSession->schoolClass);
        $recordsByStudent = $attendanceSession->records->keyBy('student_id');
        $rows = $students->map(fn ($student) => [
            'student' => $student,
            'record' => $recordsByStudent->get($student->id),
        ]);

        return view('attendance.edit', [
            'academicYear' => $attendanceSession->academicYear,
            'rows' => $rows,
            'session' => $attendanceSession,
            'summary' => $this->sessionSummary($rows),
        ]);
    }

    public function updateSession(Request $request, AttendanceSession $attendanceSession): RedirectResponse
    {
        $data = $request->validate([
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'exists:students,id'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
            'records.*.minutes_late' => ['nullable', 'integer', 'min:0', 'max:600'],
            'records.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data['records'] as $row) {
            $status = $row['status'];
            $isExcused = $status === 'excused';

            AttendanceRecord::query()->updateOrCreate(
                [
                    'attendance_session_id' => $attendanceSession->id,
                    'student_id' => $row['student_id'],
                ],
                [
                    'status' => $status,
                    'minutes_late' => $status === 'late' ? ($row['minutes_late'] ?? null) : null,
                    'reason' => $row['reason'] ?? null,
                    'justified_at' => $isExcused ? now() : null,
                    'justified_by' => $isExcused ? $request->user()->id : null,
                ],
            );
        }

        return redirect()
            ->route('attendance.sessions.edit', $attendanceSession)
            ->with('success', 'Pointage enregistre.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();

        abort_if(! $academicYear, 422, 'Aucune annee scolaire active.');

        return $academicYear;
    }

    private function classes(?AcademicYear $academicYear): Collection
    {
        return SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function selectedClass(Request $request, Collection $classes): ?SchoolClass
    {
        $selectedId = $request->integer('school_class_id');

        if ($selectedId > 0) {
            return $classes->firstWhere('id', $selectedId);
        }

        return $classes->first();
    }

    private function sessionsForDate(?AcademicYear $academicYear, $date): Collection
    {
        return AttendanceSession::query()
            ->with(['schoolClass.level', 'records'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereDate('session_date', $date)
            ->get();
    }

    private function studentsForClass(SchoolClass $schoolClass): Collection
    {
        return $schoolClass->enrollments()
            ->with('student')
            ->where('enrollments.status', 'active')
            ->whereHas('student', fn ($query) => $query->where('students.status', 'active'))
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('enrollments.*')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
    }

    private function dailySummary(Collection $sessions): array
    {
        $records = $sessions->flatMap->records;

        return $this->recordSummary($records);
    }

    private function sessionSummary(Collection $rows): array
    {
        return $this->recordSummary($rows->pluck('record')->filter());
    }

    private function recordSummary(Collection $records): array
    {
        return [
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
        ];
    }

    private function recentRecords(?AcademicYear $academicYear): Collection
    {
        return AttendanceRecord::query()
            ->with(['student', 'session.schoolClass'])
            ->whereIn('status', ['absent', 'late', 'excused'])
            ->whereHas('session', function ($query) use ($academicYear) {
                $query->when($academicYear, fn ($subQuery) => $subQuery->where('academic_year_id', $academicYear->id));
            })
            ->latest()
            ->limit(10)
            ->get();
    }
}
