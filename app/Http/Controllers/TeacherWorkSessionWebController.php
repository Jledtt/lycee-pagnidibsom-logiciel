<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherWorkSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
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
        $month = isset($filters['month']) ? Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth() : now()->startOfMonth();
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

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->activeAcademicYear();
        abort_unless($academicYear, 422, 'Configure une année scolaire active.');

        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'school_class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')->where('academic_year_id', $academicYear->id)],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'session_date' => ['required', 'date', 'after_or_equal:'.$academicYear->starts_at->toDateString(), 'before_or_equal:'.$academicYear->ends_at->toDateString()],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'hours_worked' => ['required', 'numeric', 'min:0.25', 'max:250'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['required', Rule::in(['draft', 'validated'])],
            'teacher_signed' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $teacher = User::query()->findOrFail($data['teacher_id']);
        abort_unless($teacher->hasRole('enseignant'), 422, 'Le compte choisi n’est pas un professeur.');

        TeacherWorkSession::query()->create([
            ...$data,
            'academic_year_id' => $academicYear->id,
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
}
