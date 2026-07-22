<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentExitAuthorization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentExitAuthorizationWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();

        $authorizations = StudentExitAuthorization::query()
            ->with(['student', 'schoolClass', 'creator'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->whereHas('student', function ($studentQuery) use ($search) {
                    $studentQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->latest('document_date')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('exit-authorizations.index', [
            'academicYear' => $academicYear,
            'authorizations' => $authorizations,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('exit-authorizations.create', [
            'academicYear' => $this->activeAcademicYear(),
            'students' => $this->enrolledStudents(),
            'selectedStudentId' => $request->integer('student_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'document_date' => ['required', 'date'],
            'departure_at' => ['nullable', 'date'],
            'return_at' => ['nullable', 'date', 'after_or_equal:departure_at'],
            'subject_name' => ['nullable', 'string', 'max:120'],
            'destination' => ['nullable', 'string', 'max:160'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);
        $enrollment = $this->currentEnrollment($student, $academicYear);

        if (! $enrollment) {
            return back()
                ->withErrors(['student_id' => 'Cet élève doit être inscrit dans une classe avant de générer une autorisation.'])
                ->withInput();
        }

        $authorization = StudentExitAuthorization::query()->create([
            ...$data,
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $enrollment->school_class_id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('exit-authorizations.show', $authorization)
            ->with('success', 'Autorisation d’entrée/sortie générée.');
    }

    public function show(StudentExitAuthorization $exitAuthorization): View
    {
        $exitAuthorization->load(['student', 'schoolClass', 'academicYear', 'creator']);

        return view('exit-authorizations.show', [
            'academicYear' => $this->activeAcademicYear(),
            'authorization' => $exitAuthorization,
        ]);
    }

    public function pdf(StudentExitAuthorization $exitAuthorization)
    {
        $exitAuthorization->load(['student', 'schoolClass', 'academicYear', 'creator']);
        $filename = 'autorisation-sortie-' . Str::slug($exitAuthorization->student->matricule . '-' . $exitAuthorization->document_date?->format('Y-m-d')) . '.pdf';

        return Pdf::loadView('exit-authorizations.pdf', [
            'authorization' => $exitAuthorization,
            'school' => SchoolSetting::query()->first(),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
    }

    private function enrolledStudents()
    {
        $academicYear = $this->activeAcademicYear();

        return Student::query()
            ->where('status', 'active')
            ->whereHas('enrollments', function ($query) use ($academicYear) {
                $query->when($academicYear, fn ($subQuery) => $subQuery->where('academic_year_id', $academicYear->id))
                    ->where('status', 'active');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function currentEnrollment(Student $student, AcademicYear $academicYear): ?Enrollment
    {
        return Enrollment::query()
            ->with('schoolClass')
            ->where('academic_year_id', $academicYear->id)
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }
}
