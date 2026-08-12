<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Rules\ValidClassSubjectCoefficient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeacherWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $query = $this->visibleTeachers($request)
            ->with('teacherProfile')
            ->withCount('teacherDocuments')
            ->when($request->string('search')->toString(), function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('teacherProfile', fn (Builder $profileQuery) => $profileQuery
                            ->where('specialty', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('name');

        return view('teachers.index', [
            'academicYear' => $academicYear,
            'filters' => $request->only(['search', 'status']),
            'teachers' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function show(Request $request, User $teacher): View
    {
        $this->authorizeTeacherAccess($request, $teacher);
        $academicYear = $this->activeAcademicYear();
        $teacher->load([
            'teacherProfile',
            'teacherDocuments' => fn ($query) => $query->latest(),
            'teacherFeeStatements' => fn ($query) => $query->latest('period_month')->limit(12),
            'teacherWorkSessions' => fn ($query) => $query
                ->with(['schoolClass', 'subject'])
                ->where('academic_year_id', $academicYear?->id)
                ->latest('session_date')
                ->limit(20),
        ]);

        $assignments = $teacher->hasRole('enseignant')
            ? ClassSubject::query()
                ->with(['schoolClass', 'subject'])
                ->where('teacher_id', $teacher->id)
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $academicYear?->id))
                ->get()
            : collect();

        return view('teachers.show', [
            'academicYear' => $academicYear,
            'assignments' => $assignments,
            'classes' => SchoolClass::query()->where('academic_year_id', $academicYear?->id)->orderBy('name')->get(),
            'profile' => $teacher->teacherProfile ?? new TeacherProfile(['withholding_tax_rate' => 2]),
            'subjects' => Subject::query()->where('status', 'active')->orderBy('name')->get(),
            'teacher' => $teacher,
        ]);
    }

    public function updateProfile(Request $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->hasRole('enseignant'), 404);

        $data = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('teacher_profiles')->ignore($teacher->teacherProfile)],
            'specialty' => ['nullable', 'string', 'max:160'],
            'identity_document_type' => ['nullable', Rule::in(['CNIB', 'Passeport', 'Autre'])],
            'identity_document_number' => ['nullable', 'string', 'max:80'],
            'identity_document_issued_at' => ['nullable', 'date'],
            'identity_document_expires_at' => ['nullable', 'date', 'after_or_equal:identity_document_issued_at'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact' => ['nullable', 'string', 'max:160'],
            'default_hourly_rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'withholding_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_method' => ['nullable', Rule::in(['Espèces', 'Virement', 'Mobile Money', 'Chèque'])],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'contract_type' => ['nullable', 'string', 'max:60'],
            'hired_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        TeacherProfile::query()->updateOrCreate(['user_id' => $teacher->id], $data);

        return redirect()->route('teachers.show', $teacher)->with('success', 'Dossier professeur mis à jour.');
    }

    public function storeAssignment(Request $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->hasRole('enseignant'), 404);
        $academicYear = $this->activeAcademicYear();
        abort_unless($academicYear, 422, 'Configure une année scolaire active.');
        $data = $request->validate([
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')->where('academic_year_id', $academicYear->id)],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('status', 'active')],
            'coefficient' => ['required', new ValidClassSubjectCoefficient],
            'weekly_hours' => ['nullable', 'numeric', 'min:0.25', 'max:60'],
        ]);

        ClassSubject::query()->updateOrCreate(
            [
                'school_class_id' => $data['school_class_id'],
                'subject_id' => $data['subject_id'],
            ],
            [
                'teacher_id' => $teacher->id,
                'coefficient' => $data['coefficient'],
                'weekly_hours' => $data['weekly_hours'] ?? null,
                'is_active' => true,
            ],
        );

        return back()->with('success', 'Affectation pédagogique enregistrée.');
    }

    public function destroyAssignment(User $teacher, ClassSubject $classSubject): RedirectResponse
    {
        abort_unless($teacher->hasRole('enseignant') && $classSubject->teacher_id === $teacher->id, 404);
        $classSubject->update(['teacher_id' => null]);

        return back()->with('success', 'Professeur retiré de cette affectation.');
    }

    public function pdf(Request $request, User $teacher)
    {
        $this->authorizeTeacherAccess($request, $teacher);
        $academicYear = $this->activeAcademicYear();
        $teacher->load(['teacherProfile', 'teacherDocuments']);

        return Pdf::loadView('teachers.profile-pdf', [
            'academicYear' => $academicYear,
            'assignments' => ClassSubject::query()
                ->with(['schoolClass', 'subject'])
                ->where('teacher_id', $teacher->id)
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $academicYear?->id))
                ->get(),
            'school' => SchoolSetting::query()->first(),
            'teacher' => $teacher,
        ])->setPaper('a4')->stream('dossier-professeur-'.$teacher->id.'.pdf');
    }

    private function visibleTeachers(Request $request): Builder
    {
        $query = User::query()->role('enseignant');

        if (! $request->user()->can('teachers.manage')) {
            $query->whereKey($request->user()->id);
        }

        return $query;
    }

    private function authorizeTeacherAccess(Request $request, User $teacher): void
    {
        abort_unless($teacher->hasRole('enseignant'), 404);
        abort_unless($request->user()->can('teachers.manage') || $request->user()->is($teacher), 403);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }
}
