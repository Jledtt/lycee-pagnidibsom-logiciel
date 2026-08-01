<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teacher\StoreTeacherWorkSessionRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherWorkSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherWorkSessionWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['draft', 'validated', 'cancelled'])],
        ]);
        $month = isset($filters['month'])
            ? Carbon::createFromFormat('Y-m-d', $filters['month'].'-01')->startOfMonth()
            : now()->startOfMonth();
        $defaultSessionDate = now()->startOfDay();

        if ($academicYear?->starts_at && $defaultSessionDate->lt($academicYear->starts_at)) {
            $defaultSessionDate = $academicYear->starts_at->copy();
        } elseif ($academicYear?->ends_at && $defaultSessionDate->gt($academicYear->ends_at)) {
            $defaultSessionDate = $academicYear->ends_at->copy();
        }

        $query = TeacherWorkSession::query()
            ->with(['teacher', 'schoolClass', 'subject', 'validator', 'feeLine'])
            ->where('academic_year_id', $academicYear?->id)
            ->whereBetween('session_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->when($filters['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        if (! $request->user()->can('teacher_attendance.manage')) {
            $query->where('teacher_id', $request->user()->id);
        }

        return view('teacher-work-sessions.index', [
            'academicYear' => $academicYear,
            'defaultSessionDate' => $defaultSessionDate->toDateString(),
            'classes' => SchoolClass::query()->where('academic_year_id', $academicYear?->id)->orderBy('name')->get(),
            'filters' => [
                'month' => $month->format('Y-m'),
                'status' => $filters['status'] ?? '',
                'teacher_id' => $filters['teacher_id'] ?? null,
            ],
            'sessions' => $query->latest('session_date')->latest('starts_at')->paginate(30)->withQueryString(),
            'subjects' => Subject::query()->where('status', 'active')->orderBy('name')->get(),
            'teachers' => $this->visibleTeachers($request),
        ]);
    }

    public function store(StoreTeacherWorkSessionRequest $request): RedirectResponse
    {
        $academicYear = $this->activeAcademicYear();
        abort_unless($academicYear, 422, 'Configure une année scolaire active.');
        $data = $request->validated();

        $teacher = User::query()->findOrFail($data['teacher_id']);
        abort_unless($teacher->hasRole('enseignant'), 422, 'Le compte choisi n’est pas un professeur.');

        $assigned = ClassSubject::query()
            ->where('school_class_id', $data['school_class_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->exists();

        if (! $assigned) {
            $this->failAndReopen($request, 'subject_id', 'Ce professeur n’est pas affecté à cette matière dans cette classe.');
        }

        $startsAt = Carbon::createFromFormat('H:i', $data['starts_at'])->format('H:i:s');
        $endsAt = Carbon::createFromFormat('H:i', $data['ends_at'])->format('H:i:s');
        $teacherDaySessions = TeacherWorkSession::query()
            ->where('teacher_id', $teacher->id)
            ->whereDate('session_date', $data['session_date'])
            ->where('status', '!=', 'cancelled');

        $duplicateExists = (clone $teacherDaySessions)
            ->where('school_class_id', $data['school_class_id'])
            ->where('subject_id', $data['subject_id'])
            ->whereTime('starts_at', $startsAt)
            ->whereTime('ends_at', $endsAt)
            ->exists();

        if ($duplicateExists) {
            $this->failAndReopen($request, 'starts_at', 'Cette séance a déjà été enregistrée.');
        }

        $overlapExists = (clone $teacherDaySessions)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereTime('starts_at', '<', $endsAt)
            ->whereTime('ends_at', '>', $startsAt)
            ->exists();

        if ($overlapExists) {
            $this->failAndReopen($request, 'starts_at', 'Ce professeur a déjà un cours sur tout ou partie de ce créneau.');
        }

        TeacherWorkSession::query()->create([
            ...$data,
            'academic_year_id' => $academicYear->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'teacher_signed_at' => $request->boolean('teacher_signed') ? now() : null,
            'validated_at' => $data['status'] === 'validated' ? now() : null,
            'validated_by' => $data['status'] === 'validated' ? $request->user()->id : null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Heures du professeur enregistrées.');
    }

    public function validateSession(Request $request, TeacherWorkSession $teacherWorkSession): RedirectResponse
    {
        abort_if($teacherWorkSession->feeLine()->exists(), 422, 'Cette ligne est déjà rattachée à un honoraire.');
        $teacherWorkSession->update([
            'status' => 'validated',
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Émargement validé.');
    }

    public function destroy(TeacherWorkSession $teacherWorkSession): RedirectResponse
    {
        abort_if($teacherWorkSession->feeLine()->exists(), 422, 'Une heure déjà rattachée à un honoraire ne peut pas être supprimée.');
        $teacherWorkSession->delete();

        return back()->with('success', 'Ligne d’émargement supprimée.');
    }

    private function visibleTeachers(Request $request)
    {
        $query = User::query()->role('enseignant')->where('status', 'active')->orderBy('name');

        if (! $request->user()->can('teacher_attendance.manage')) {
            $query->whereKey($request->user()->id);
        }

        return $query->get();
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function failAndReopen(Request $request, string $field, string $message): never
    {
        $request->session()->flash('teacher_work_session_open', true);

        throw ValidationException::withMessages([$field => $message]);
    }
}
