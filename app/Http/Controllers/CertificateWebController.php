<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\OfficialNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CertificateWebController extends Controller
{
    public const TYPES = [
        'school_certificate' => 'Certificat de scolarité',
        'enrollment_certificate' => 'Certificat d inscription',
        'no_debt_certificate' => 'Certificat de non redevance',
    ];

    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();

        $documents = StudentDocument::query()
            ->with(['student', 'academicYear'])
            ->whereIn('document_type', array_keys(self::TYPES))
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($request->string('type')->toString(), fn ($query, string $type) => $query->where('document_type', $type))
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

        return view('certificates.index', [
            'academicYear' => $academicYear,
            'documents' => $documents,
            'types' => self::TYPES,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('certificates.create', [
            'academicYear' => $this->activeAcademicYear(),
            'types' => self::TYPES,
            'students' => $this->enrolledStudents(),
            'selectedStudentId' => $request->integer('student_id'),
        ]);
    }

    public function store(Request $request, OfficialNumberService $officialNumberService): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'document_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'received_at' => ['nullable', 'date'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $enrollment = $this->currentEnrollment($student, $academicYear);

        if (! $enrollment) {
            return back()
                ->withErrors(['student_id' => 'Cet élève doit être inscrit dans une classe avant de générer un certificat.'])
                ->withInput();
        }

        if ($data['document_type'] === 'no_debt_certificate') {
            $summary = $this->studentPaymentSummary($student, $academicYear);

            if (! is_null($summary['balance']) && $summary['balance'] > 0) {
                return back()
                    ->withErrors(['document_type' => 'Impossible de générer un certificat de non-redevance : cet élève a encore un reste à payer.'])
                    ->withInput();
            }
        }

        $document = StudentDocument::create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'name' => self::TYPES[$data['document_type']] . ' - ' . $student->full_name,
            'document_type' => $data['document_type'],
            'document_number' => $officialNumberService->generate(
                OfficialNumberService::STUDENT_CERTIFICATE,
                fn (string $number) => StudentDocument::query()->where('document_number', $number)->exists(),
                $academicYear,
            ),
            'status' => 'received',
            'received_at' => $data['received_at'] ?? now()->toDateString(),
        ]);

        return redirect()
            ->route('certificates.show', $document)
            ->with('success', 'Certificat généré avec succès.');
    }

    public function show(StudentDocument $certificate): View
    {
        abort_unless(array_key_exists($certificate->document_type, self::TYPES), 404);

        $certificate->load(['student.guardians', 'academicYear']);
        $enrollment = $this->currentEnrollment($certificate->student, $certificate->academicYear);

        return view('certificates.show', [
            'academicYear' => $this->activeAcademicYear(),
            'certificate' => $certificate,
            'typeLabel' => self::TYPES[$certificate->document_type],
            'enrollment' => $enrollment,
            'summary' => $this->studentPaymentSummary($certificate->student, $certificate->academicYear),
        ]);
    }

    public function pdf(StudentDocument $certificate)
    {
        abort_unless(array_key_exists($certificate->document_type, self::TYPES), 404);

        $certificate->load(['student.guardians', 'academicYear']);
        $student = $certificate->student;
        $enrollment = $this->currentEnrollment($student, $certificate->academicYear);
        $filename = str($certificate->document_type . '-' . $student->matricule . '-' . $student->full_name)->slug() . '.pdf';

        return Pdf::loadView('certificates.certificate-pdf', [
            'certificate' => $certificate,
            'typeLabel' => self::TYPES[$certificate->document_type],
            'student' => $student,
            'enrollment' => $enrollment,
            'school' => SchoolSetting::query()->first(),
            'fatherGuardian' => $this->guardian($student, 'father') ?? $this->guardian($student, 'tutor'),
            'motherGuardian' => $this->guardian($student, 'mother'),
            'summary' => $this->studentPaymentSummary($student, $certificate->academicYear),
            'principalName' => SchoolSetting::query()->value('principal_name') ?: 'Yamdaogo TINTILA',
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

    private function currentEnrollment(Student $student, ?AcademicYear $academicYear): ?Enrollment
    {
        return Enrollment::query()
            ->with('schoolClass.level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    private function guardian(Student $student, string $relationship)
    {
        return $student->guardians->firstWhere('pivot.relationship', $relationship);
    }

    private function studentPaymentSummary(Student $student, ?AcademicYear $academicYear): array
    {
        $enrollment = $this->currentEnrollment($student, $academicYear);
        $expected = null;

        if ($enrollment) {
            $scheduledAmount = FeeSchedule::query()
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->where('school_class_id', $enrollment->school_class_id)
                ->sum('amount');

            $expected = $scheduledAmount > 0 ? (float) $scheduledAmount : null;
        }

        $paid = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('student_id', $student->id)
            ->where('status', 'valid')
            ->sum('amount');

        return [
            'expected' => $expected,
            'paid' => (float) $paid,
            'balance' => is_null($expected) ? null : max($expected - (float) $paid, 0),
        ];
    }
}
