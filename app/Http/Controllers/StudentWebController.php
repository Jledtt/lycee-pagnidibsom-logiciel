<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\CommunicationService;
use App\Services\MatriculeGeneratorService;
use App\Services\RequiredStudentDocumentService;
use App\Services\XlsxExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentWebController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = $request->integer('per_page', 12);
        $perPage = in_array($perPage, [12, 25, 50, 100], true) ? $perPage : 12;

        $students = Student::query()
            ->with(['guardians', 'enrollments.schoolClass'])
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('students.index', [
            'academicYear' => $this->activeAcademicYear(),
            'students' => $students,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function create(): View
    {
        return view('students.create', [
            'academicYear' => $this->activeAcademicYear(),
            'student' => new Student(['status' => 'active']),
            'fatherGuardian' => null,
            'motherGuardian' => null,
        ]);
    }

    public function export(Request $request, XlsxExportService $xlsxExport)
    {
        $students = $this->studentQuery($request)
            ->with(['guardians', 'enrollments.schoolClass'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $xlsxExport->download('eleves-'.now()->format('Ymd-His').'.xlsx', [
            'Matricule',
            'Nom',
            'Prénom',
            'Sexe',
            'Classe',
            'Date naissance',
            'Lieu naissance',
            'Téléphone domicile',
            'Tuteur',
            'Contact tuteur',
            'Statut',
        ], $students->map(function (Student $student) {
            $enrollment = $student->enrollments->sortByDesc('id')->first();
            $guardian = $student->guardians->first();

            return [
                $student->matricule,
                $student->last_name,
                $student->first_name,
                $student->gender_label,
                $student->desired_class ?: ($enrollment?->schoolClass?->name ?? ''),
                $student->birth_date?->format('d/m/Y'),
                $student->birth_place,
                $student->home_phone,
                $guardian?->full_name,
                $guardian?->phone_primary,
                $student->status,
            ];
        }));
    }

    public function store(Request $request, MatriculeGeneratorService $matriculeGenerator): RedirectResponse
    {
        $data = $this->validateStudent($request);
        $guardianData = $this->validateGuardians($request);
        $academicYear = $this->activeAcademicYear();

        $student = DB::transaction(function () use ($data, $guardianData, $matriculeGenerator, $academicYear) {
            $student = Student::create($data + [
                'matricule' => $matriculeGenerator->generate($academicYear),
                'status' => 'active',
            ]);

            $this->attachGuardianIfPresent($student, $guardianData, 'father', 'father');
            $this->attachGuardianIfPresent($student, $guardianData, 'mother', 'mother');

            return $student;
        });

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Élève ajouté avec succès.');
    }

    public function show(Student $student, RequiredStudentDocumentService $requiredDocuments): View
    {
        $student->load([
            'guardians',
            'enrollments.schoolClass.level',
            'payments.lines.feeType',
            'attendanceRecords.session',
            'documents.academicYear',
        ]);

        $currentEnrollment = $student->enrollments->sortByDesc('id')->first();
        $currentClass = $currentEnrollment?->schoolClass;

        return view('students.show', [
            'academicYear' => $this->activeAcademicYear(),
            'student' => $student,
            'currentEnrollment' => $currentEnrollment,
            'documentTypeLabels' => $requiredDocuments->availableDocumentTypes(),
            'requiredDocumentStatuses' => $requiredDocuments->statusForStudent($student, $currentClass),
            'missingRequiredDocuments' => $requiredDocuments->missingForStudent($student, $currentClass),
        ]);
    }

    public function registrationSheet(Student $student): View
    {
        return view('students.registration-sheet', $this->registrationSheetData($student));
    }

    public function registrationSheetPdf(Student $student)
    {
        $data = $this->registrationSheetData($student);
        $filename = 'fiche-inscription-'.Str::slug($student->matricule.'-'.$student->full_name).'.pdf';

        return Pdf::loadView('students.registration-sheet-pdf', $data)
            ->setPaper('a4')
            ->stream($filename);
    }

    public function edit(Student $student): View
    {
        $student->load('guardians');

        return view('students.edit', [
            'academicYear' => $this->activeAcademicYear(),
            'student' => $student,
            'fatherGuardian' => $student->guardians->firstWhere('pivot.relationship', 'father')
                ?? $student->guardians->firstWhere('pivot.relationship', 'tutor'),
            'motherGuardian' => $student->guardians->firstWhere('pivot.relationship', 'mother'),
        ]);
    }

    public function update(
        Request $request,
        Student $student,
        CommunicationService $communicationService,
    ): RedirectResponse
    {
        $data = $this->validateStudent($request, true);
        $guardianData = $this->validateGuardians($request);
        $oldStatus = (string) $student->status;

        DB::transaction(function () use ($student, $data, $guardianData) {
            $student->update($data);
            $this->attachGuardianIfPresent($student, $guardianData, 'father', 'father');
            $this->attachGuardianIfPresent($student, $guardianData, 'mother', 'mother');
        });

        $student->refresh();
        $communicationService->queueStudentStatusChange(
            $student,
            $oldStatus,
            (string) $student->status,
            $request->user(),
        );

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Fiche élève mise à jour.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()
            ->route('students.index')
            ->with('success', 'Élève archivé avec succès.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function studentQuery(Request $request)
    {
        return Student::query()
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status));
    }

    private function registrationSheetData(Student $student): array
    {
        $student->load(['guardians', 'enrollments.schoolClass']);

        return [
            'student' => $student,
            'academicYear' => $this->activeAcademicYear(),
            'fatherGuardian' => $student->guardians->firstWhere('pivot.relationship', 'father')
                ?? $student->guardians->firstWhere('pivot.relationship', 'tutor'),
            'motherGuardian' => $student->guardians->firstWhere('pivot.relationship', 'mother'),
        ];
    }

    private function validateStudent(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'first_name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'last_name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'desired_class' => ['nullable', 'string', 'max:255'],
            'origin_school' => ['nullable', 'string', 'max:255'],
            'previous_class' => ['nullable', 'string', 'max:255'],
            'repeated_class' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'ethnicity' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'home_phone' => ['nullable', 'string', 'max:40'],
            'health_notes' => ['nullable', 'string'],
            'health_conditions' => ['nullable', 'array'],
            'health_conditions.*' => ['string', 'in:asthme,drepanocytose,cardiopathie,hta,diabete,epilepsie'],
            'sport_aptitude' => ['nullable', 'boolean'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'school_info_whatsapp' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'in:active,transferred,dropped,graduated,suspended'],
        ]);
    }

    private function validateGuardians(Request $request): array
    {
        return $request->validate([
            'father_first_name' => ['nullable', 'string', 'max:255'],
            'father_last_name' => ['nullable', 'string', 'max:255'],
            'father_phone_primary' => ['nullable', 'string', 'max:40'],
            'father_email' => ['nullable', 'email', 'max:255'],
            'father_profession' => ['nullable', 'string', 'max:255'],
            'father_service' => ['nullable', 'string', 'max:255'],
            'mother_first_name' => ['nullable', 'string', 'max:255'],
            'mother_last_name' => ['nullable', 'string', 'max:255'],
            'mother_phone_primary' => ['nullable', 'string', 'max:40'],
            'mother_email' => ['nullable', 'email', 'max:255'],
            'mother_profession' => ['nullable', 'string', 'max:255'],
            'mother_service' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function attachGuardianIfPresent(Student $student, array $data, string $prefix, string $relationship): void
    {
        if (blank($data[$prefix.'_phone_primary'] ?? null) || blank($data[$prefix.'_last_name'] ?? null)) {
            return;
        }

        $guardian = Guardian::firstOrCreate(
            ['phone_primary' => $data[$prefix.'_phone_primary']],
            [
                'first_name' => $data[$prefix.'_first_name'] ?? '',
                'last_name' => $data[$prefix.'_last_name'],
                'email' => $data[$prefix.'_email'] ?? null,
                'profession' => $data[$prefix.'_profession'] ?? null,
                'service' => $data[$prefix.'_service'] ?? null,
                'status' => 'active',
            ]
        );

        $guardian->update([
            'first_name' => $data[$prefix.'_first_name'] ?? $guardian->first_name,
            'last_name' => $data[$prefix.'_last_name'],
            'email' => $data[$prefix.'_email'] ?? $guardian->email,
            'profession' => $data[$prefix.'_profession'] ?? $guardian->profession,
            'service' => $data[$prefix.'_service'] ?? $guardian->service,
        ]);

        if (! $student->guardians()->whereKey($guardian->id)->exists()) {
            $student->guardians()->attach($guardian->id, [
                'relationship' => $relationship,
                'is_primary' => $relationship === 'father',
                'can_receive_sms' => true,
                'can_pickup_child' => false,
            ]);
        } else {
            $student->guardians()->updateExistingPivot($guardian->id, [
                'relationship' => $relationship,
                'is_primary' => $relationship === 'father',
            ]);
        }
    }
}
