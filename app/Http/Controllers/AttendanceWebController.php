<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\XlsxExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
            ? $sessions->where('school_class_id', $schoolClass->id)->sortByDesc('updated_at')->first()
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

    public function pdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $date = $request->date('date') ?? today();
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $session = AttendanceSession::query()
            ->with(['schoolClass.level', 'records.student', 'academicYear'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->whereDate('session_date', $date)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        return $this->attendancePdfResponse($session, $schoolClass, $academicYear, $date);
    }

    public function export(Request $request, XlsxExportService $xlsxExport)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $date = $request->date('date') ?? today();
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $session = AttendanceSession::query()
            ->with(['schoolClass.level', 'records.student', 'academicYear'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->whereDate('session_date', $date)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        $records = $session
            ? $session->records
                ->filter(fn (AttendanceRecord $record) => in_array($record->status, ['absent', 'late', 'excused'], true))
                ->sortBy(fn (AttendanceRecord $record) => Str::lower($record->student?->full_name ?? ''))
                ->values()
            : collect();

        return $xlsxExport->download('absences-' . Str::slug($schoolClass->name . '-' . $date->format('Y-m-d')) . '.xlsx', [
            'Date',
            'Classe',
            'Matricule',
            'Eleve',
            'Statut',
            'Minutes retard',
            'Motif',
        ], $records->map(fn (AttendanceRecord $record) => [
            $date->format('d/m/Y'),
            $schoolClass->name,
            $record->student?->matricule,
            $record->student?->full_name,
            $record->status,
            $record->status === 'late' ? ($record->minutes_late ?? 0) : '',
            $record->reason,
        ]));
    }

    public function sessionPdf(AttendanceSession $attendanceSession)
    {
        $attendanceSession->load(['schoolClass.level', 'records.student', 'academicYear']);

        return $this->attendancePdfResponse(
            $attendanceSession,
            $attendanceSession->schoolClass,
            $attendanceSession->academicYear,
            $attendanceSession->session_date,
        );
    }

    public function studentHistory(Request $request, Student $student): View
    {
        $academicYear = $this->activeAcademicYear();
        [$month, $start, $end] = $this->monthPeriod($request);
        $records = $this->studentAttendanceRecords($student, $academicYear, $start, $end);

        return view('attendance.student-history', [
            'academicYear' => $academicYear,
            'month' => $month,
            'records' => $records,
            'student' => $student,
            'summary' => $this->recordSummary($records),
        ]);
    }

    public function studentHistoryPdf(Request $request, Student $student)
    {
        $academicYear = $this->activeAcademicYear();
        [$month, $start, $end] = $this->monthPeriod($request);
        $records = $this->studentAttendanceRecords($student, $academicYear, $start, $end);
        $filename = 'assiduite-' . Str::slug($student->matricule . '-' . $month) . '.pdf';

        return Pdf::loadView('attendance.student-history-pdf', [
            'academicYear' => $academicYear,
            'month' => $month,
            'records' => $records,
            'school' => SchoolSetting::query()->first(),
            'student' => $student,
            'summary' => $this->recordSummary($records),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function clearRecord(AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $attendanceRecord->forceFill([
            'status' => 'present',
            'minutes_late' => null,
            'reason' => null,
            'justified_at' => null,
            'justified_by' => null,
        ])->save();

        return redirect()
            ->back()
            ->with('success', 'Absence supprimee. L eleve est marque present.');
    }

    private function attendancePdfResponse(?AttendanceSession $session, SchoolClass $schoolClass, ?AcademicYear $academicYear, $date)
    {
        $records = $session
            ? $session->records
                ->filter(fn (AttendanceRecord $record) => in_array($record->status, ['absent', 'late', 'excused'], true))
                ->sortBy(fn (AttendanceRecord $record) => Str::lower($record->student?->full_name ?? ''))
                ->values()
            : collect();

        $filename = 'absences-' . Str::slug($schoolClass->name . '-' . $date->format('Y-m-d')) . '.pdf';

        return Pdf::loadView('attendance.pdf', [
            'academicYear' => $academicYear,
            'date' => $date,
            'records' => $records,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'session' => $session,
            'summary' => $this->recordSummary($session?->records ?? collect()),
        ])
            ->setPaper('a4')
            ->stream($filename);
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
            ->latest('updated_at')
            ->latest('id')
            ->get()
            ->unique('school_class_id')
            ->values();
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

    private function monthPeriod(Request $request): array
    {
        $month = $request->input('month') ?: now()->format('Y-m');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$month, $start, $end];
    }

    private function studentAttendanceRecords(Student $student, ?AcademicYear $academicYear, Carbon $start, Carbon $end): Collection
    {
        return AttendanceRecord::query()
            ->with(['session.schoolClass.level', 'justifiedBy'])
            ->where('student_id', $student->id)
            ->whereIn('attendance_records.status', ['absent', 'late', 'excused'])
            ->whereHas('session', function ($query) use ($academicYear, $start, $end) {
                $query
                    ->when($academicYear, fn ($subQuery) => $subQuery->where('academic_year_id', $academicYear->id))
                    ->whereBetween('session_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->orderByDesc('attendance_sessions.session_date')
            ->orderByDesc('attendance_records.id')
            ->select('attendance_records.*')
            ->get();
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
