<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\PaymentFinancialProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnrollmentWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();

        $enrollments = Enrollment::query()
            ->with(['student.guardians', 'schoolClass.level', 'academicYear'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($request->integer('school_class_id'), fn ($query, int $classId) => $query->where('school_class_id', $classId))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->whereHas('student', function ($studentQuery) use ($search) {
                    $studentQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('enrollments.index', [
            'academicYear' => $academicYear,
            'enrollments' => $enrollments,
            'classes' => $this->activeClasses($academicYear),
            'filters' => $request->only(['search', 'school_class_id', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $students = $this->availableStudents($academicYear);
        $selectedStudentId = $request->integer('student_id');

        return view('enrollments.create', [
            'academicYear' => $academicYear,
            'enrollment' => new Enrollment([
                'type' => 'new',
                'status' => 'active',
                'enrollment_date' => now()->toDateString(),
                'student_id' => $students->contains('id', $selectedStudentId) ? $selectedStudentId : null,
            ]),
            'students' => $students,
            'classes' => $this->activeClasses($academicYear),
        ]);
    }

    public function store(Request $request, EnrollmentService $enrollmentService): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $this->validateEnrollment($request, $academicYear->id);

        $student = Student::findOrFail($data['student_id']);
        $schoolClass = SchoolClass::findOrFail($data['school_class_id']);

        $enrollment = $enrollmentService->enroll($student, $schoolClass, $academicYear, $data + [
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'Inscription enregistrée avec succès.')
            ->with('enrollment_created', true);
    }

    public function show(
        Request $request,
        Enrollment $enrollment,
        PaymentFinancialProfileService $financialProfiles,
    ): View {
        $enrollment->load(['student.guardians', 'schoolClass.level', 'academicYear', 'creator']);
        $activeAcademicYear = $this->activeAcademicYear();
        $canStartPayment = $request->user()->can('payments.create')
            && $enrollment->status === 'active'
            && (int) $enrollment->academic_year_id === (int) $activeAcademicYear?->id;

        return view('enrollments.show', [
            'academicYear' => $activeAcademicYear,
            'enrollment' => $enrollment,
            'canStartPayment' => $canStartPayment,
            'paymentForm' => $canStartPayment
                ? $financialProfiles->paymentFormData($activeAcademicYear, $enrollment->student)
                : null,
            'financialSummary' => $request->user()->can('payments.view')
                ? $financialProfiles->studentPaymentSummary($enrollment->student, $enrollment->academicYear)
                : null,
        ]);
    }

    public function edit(Enrollment $enrollment): View
    {
        $academicYear = $enrollment->academicYear;

        return view('enrollments.edit', [
            'academicYear' => $academicYear,
            'enrollment' => $enrollment,
            'students' => Student::query()->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get(),
            'classes' => $this->activeClasses($academicYear),
        ]);
    }

    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $data = $this->validateEnrollment($request, $enrollment->academic_year_id, true);
        $enrollment->update($data);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'Inscription mise à jour.');
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $enrollment->update(['status' => 'cancelled']);

        return redirect()
            ->route('enrollments.index')
            ->with('success', 'Inscription annulée.');
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

    private function activeClasses(?AcademicYear $academicYear)
    {
        return SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function availableStudents(?AcademicYear $academicYear)
    {
        return Student::query()
            ->where('status', 'active')
            ->whereDoesntHave('enrollments', function ($query) use ($academicYear) {
                $query->when($academicYear, fn ($subQuery) => $subQuery->where('academic_year_id', $academicYear->id))
                    ->where('status', 'active');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function validateEnrollment(Request $request, int $academicYearId, bool $updating = false): array
    {
        return $request->validate([
            'student_id' => [$updating ? 'sometimes' : 'required', 'exists:students,id'],
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')
                    ->where('academic_year_id', $academicYearId),
            ],
            'enrollment_date' => ['nullable', 'date'],
            'type' => ['required', 'in:new,renewal,transfer'],
            'status' => ['required', 'in:active,pending,cancelled,completed'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
