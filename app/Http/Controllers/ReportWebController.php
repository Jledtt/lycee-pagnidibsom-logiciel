<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\PaymentLine;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Services\RequiredStudentDocumentService;
use App\Services\XlsxExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportWebController extends Controller
{
    public function classList(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        if ($schoolClass) {
            $schoolClass = $this->loadClassList($schoolClass);
        }

        return view('reports.class-list', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id']),
            'schoolClass' => $schoolClass,
            'summary' => $this->classSummary($schoolClass),
        ]);
    }

    public function classListPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $filename = 'liste-eleves-'.Str::slug($schoolClass->name.'-'.($academicYear?->name ?? 'annee')).'.pdf';

        return Pdf::loadView('reports.class-list-pdf', [
            'academicYear' => $academicYear,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $this->classSummary($schoolClass),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    public function classListExport(Request $request, XlsxExportService $xlsxExport)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $filename = 'liste-eleves-'.Str::slug($schoolClass->name.'-'.($academicYear?->name ?? 'annee')).'.xlsx';

        return $xlsxExport->download($filename, [
            'No',
            'Matricule',
            'Nom et prenom',
            'Sexe',
            'Date naissance',
            'Tuteur',
            'Contact',
            'Classe',
        ], $schoolClass->enrollments->values()->map(function ($enrollment, int $index) use ($schoolClass) {
            $student = $enrollment->student;
            $guardian = $student?->guardians->first();

            return [
                $index + 1,
                $student?->matricule,
                $student?->full_name,
                $student?->gender_label ?? 'Non renseigné',
                $student?->birth_date?->format('d/m/Y'),
                $guardian?->full_name,
                $guardian?->phone_primary ?? $student?->home_phone,
                $schoolClass->name,
            ];
        }));
    }

    public function paymentSituation(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        if ($schoolClass) {
            $schoolClass = $this->loadClassList($schoolClass);
        }

        $allRows = $this->paymentRows($schoolClass, $academicYear);
        $rows = $this->filterPaymentRows($allRows, $request);

        return view('reports.payment-situation', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id', 'search', 'status']),
            'rows' => $rows,
            'schoolClass' => $schoolClass,
            'summary' => $this->paymentSummary($allRows),
        ]);
    }

    public function paymentSituationPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $rows = $this->paymentRows($schoolClass, $academicYear);
        $filename = 'situation-paiements-'.Str::slug($schoolClass->name.'-'.($academicYear?->name ?? 'annee')).'.pdf';

        return Pdf::loadView('reports.payment-situation-pdf', [
            'academicYear' => $academicYear,
            'rows' => $rows,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $this->paymentSummary($rows),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    public function paymentSituationExport(Request $request, XlsxExportService $xlsxExport)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $rows = $this->filterPaymentRows($this->paymentRows($schoolClass, $academicYear), $request);
        $filename = 'situation-paiements-'.Str::slug($schoolClass->name.'-'.($academicYear?->name ?? 'annee')).'.xlsx';

        return $xlsxExport->download($filename, [
            'Matricule',
            'Élève',
            'Classe',
            'Contact',
            'Attendu',
            'Paye',
            'Reste',
            'Progression',
            'Statut',
            'Dernier paiement',
        ], $rows->map(fn (array $row) => [
            $row['student']?->matricule,
            $row['student']?->full_name,
            $row['class'] ?? $schoolClass->name,
            $row['contact'] ?? '',
            $row['expected'] ?? 'À configurer',
            $row['paid'],
            $row['balance'] ?? 'À configurer',
            $row['progress'].'%',
            $row['status']['label'],
            $row['last_payment_at']?->format('d/m/Y H:i') ?? '',
        ]));
    }

    public function installmentSituation(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        if ($schoolClass) {
            $schoolClass = $this->loadClassList($schoolClass);
        }

        $rows = $this->installmentRows($schoolClass, $academicYear);
        $studentRows = $this->installmentStudentRows($rows);

        return view('reports.installments', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id', 'search', 'status']),
            'rows' => $rows,
            'schoolClass' => $schoolClass,
            'summary' => $this->installmentSummary($rows),
            'studentRows' => $this->filterInstallmentStudentRows($studentRows, $request),
            'studentSummary' => $this->installmentStudentSummary($studentRows),
        ]);
    }

    public function installmentSituationPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $rows = $this->installmentRows($schoolClass, $academicYear);
        $filename = 'tranches-paiement-'.Str::slug($schoolClass->name.'-'.($academicYear?->name ?? 'annee')).'.pdf';

        return Pdf::loadView('reports.installments-pdf', [
            'academicYear' => $academicYear,
            'rows' => $rows,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $this->installmentSummary($rows),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    public function missingDocuments(Request $request, RequiredStudentDocumentService $requiredDocuments): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedOptionalClass($request, $classes);
        $rows = $this->filterMissingDocumentRows(
            $this->missingDocumentRows($schoolClass, $classes, $academicYear, $requiredDocuments),
            $request
        );

        return view('reports.missing-documents', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id', 'search', 'status']),
            'requiredDocuments' => $this->requiredDocumentLabelsForReport($schoolClass, $requiredDocuments),
            'rows' => $rows,
            'schoolClass' => $schoolClass,
            'summary' => $requiredDocuments->summary($rows),
        ]);
    }

    public function missingDocumentsPdf(Request $request, RequiredStudentDocumentService $requiredDocuments)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedOptionalClass($request, $classes);
        $rows = $this->filterMissingDocumentRows(
            $this->missingDocumentRows($schoolClass, $classes, $academicYear, $requiredDocuments),
            $request
        );
        $scope = $schoolClass?->name ?? 'toutes-classes';
        $filename = 'pieces-manquantes-'.Str::slug($scope.'-'.($academicYear?->name ?? 'annee')).'.pdf';

        return Pdf::loadView('reports.missing-documents-pdf', [
            'academicYear' => $academicYear,
            'requiredDocuments' => $this->requiredDocumentLabelsForReport($schoolClass, $requiredDocuments),
            'rows' => $rows,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $requiredDocuments->summary($rows),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    public function missingDocumentsExport(Request $request, RequiredStudentDocumentService $requiredDocuments, XlsxExportService $xlsxExport)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedOptionalClass($request, $classes);
        $rows = $this->filterMissingDocumentRows(
            $this->missingDocumentRows($schoolClass, $classes, $academicYear, $requiredDocuments),
            $request
        );
        $scope = $schoolClass?->name ?? 'toutes-classes';
        $filename = 'pieces-manquantes-'.Str::slug($scope.'-'.($academicYear?->name ?? 'annee')).'.xlsx';

        return $xlsxExport->download($filename, [
            'Matricule',
            'Élève',
            'Classe',
            'Statut dossier',
            'Nombre manquant',
            'Pièces manquantes',
        ], $rows->map(fn (array $row) => [
            $row['student']?->matricule,
            $row['student']?->full_name,
            $row['class']?->name,
            $row['is_complete'] ? 'Complet' : 'Incomplet',
            $row['missing_count'],
            collect($row['missing_documents'])->pluck('label')->implode(', '),
        ]), 'Pièces manquantes');
    }

    public function incompleteStudents(Request $request, RequiredStudentDocumentService $requiredDocuments): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedOptionalClass($request, $classes);
        $allRows = $this->incompleteStudentRows($schoolClass, $classes, $academicYear, $requiredDocuments);
        $rows = $this->filterIncompleteStudentRows($allRows, $request);

        return view('reports.incomplete-students', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id', 'search', 'status', 'issue']),
            'rows' => $rows,
            'schoolClass' => $schoolClass,
            'summary' => $this->incompleteStudentSummary($allRows),
        ]);
    }

    public function incompleteStudentsExport(Request $request, RequiredStudentDocumentService $requiredDocuments, XlsxExportService $xlsxExport)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedOptionalClass($request, $classes);
        $rows = $this->filterIncompleteStudentRows(
            $this->incompleteStudentRows($schoolClass, $classes, $academicYear, $requiredDocuments),
            $request
        );
        $scope = $schoolClass?->name ?? 'toutes-classes';
        $filename = 'donnees-eleves-incompletes-'.Str::slug($scope.'-'.($academicYear?->name ?? 'annee')).'.xlsx';

        return $xlsxExport->download($filename, [
            'Matricule',
            'Élève',
            'Classe',
            'Sexe',
            'Date naissance',
            'Contact',
            'Photo',
            'Pièces obligatoires',
            'Statut',
            'À compléter',
        ], $rows->map(fn (array $row) => [
            $row['student']?->matricule,
            $row['student']?->full_name,
            $row['class']?->name,
            $row['student']?->gender_label ?? 'Non renseigné',
            $row['student']?->birth_date?->format('d/m/Y') ?? '',
            $row['has_contact'] ? 'Oui' : 'Non',
            $row['has_photo'] ? 'Oui' : 'Non',
            collect($row['missing_documents'])->pluck('label')->implode(', '),
            $row['is_complete'] ? 'Complet' : 'Incomplet',
            collect($row['issues'])->pluck('label')->implode(', '),
        ]), 'Données incomplètes');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function classes(?AcademicYear $academicYear)
    {
        return SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function selectedClass(Request $request, $classes): ?SchoolClass
    {
        $selectedId = $request->integer('school_class_id');

        if ($selectedId > 0) {
            return $classes->firstWhere('id', $selectedId);
        }

        return $classes->first();
    }

    private function selectedOptionalClass(Request $request, $classes): ?SchoolClass
    {
        $selectedId = $request->integer('school_class_id');

        if ($selectedId > 0) {
            return $classes->firstWhere('id', $selectedId);
        }

        return null;
    }

    private function loadClassList(SchoolClass $schoolClass): SchoolClass
    {
        return $schoolClass->load([
            'level',
            'academicYear',
            'enrollments' => fn ($query) => $query
                ->with(['student.guardians'])
                ->where('enrollments.status', 'active')
                ->whereHas('student', fn ($studentQuery) => $studentQuery->where('status', 'active'))
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->orderBy('students.last_name')
                ->orderBy('students.first_name')
                ->select('enrollments.*'),
        ]);
    }

    private function classSummary(?SchoolClass $schoolClass): array
    {
        if (! $schoolClass) {
            return [
                'total' => 0,
                'girls' => 0,
                'boys' => 0,
            ];
        }

        $students = $schoolClass->enrollments->pluck('student')->filter();

        return [
            'total' => $students->count(),
            'girls' => $students->where('gender', 'female')->count(),
            'boys' => $students->where('gender', 'male')->count(),
        ];
    }

    private function paymentRows(?SchoolClass $schoolClass, ?AcademicYear $academicYear): Collection
    {
        if (! $schoolClass) {
            return collect();
        }

        $expected = $this->expectedAmount($schoolClass, $academicYear);
        $studentIds = $schoolClass->enrollments->pluck('student_id')->all();

        $validPayments = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereIn('student_id', $studentIds)
            ->where('status', 'valid')
            ->get();

        $paidByStudent = $validPayments
            ->groupBy('student_id')
            ->map(fn (Collection $payments) => (float) $payments->sum('amount'));

        return $schoolClass->enrollments
            ->map(function ($enrollment) use ($expected, $paidByStudent, $validPayments) {
                $paid = (float) ($paidByStudent[$enrollment->student_id] ?? 0);
                $balance = is_null($expected) ? null : max($expected - $paid, 0);
                $student = $enrollment->student;
                $lastPaymentAt = $validPayments
                    ->where('student_id', $enrollment->student_id)
                    ->sortByDesc('paid_at')
                    ->first()?->paid_at;

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'expected' => $expected,
                    'paid' => $paid,
                    'balance' => $balance,
                    'progress' => $expected ? min((int) round(($paid / $expected) * 100), 100) : 0,
                    'contact' => $this->studentPaymentContact($student),
                    'last_payment_at' => $lastPaymentAt,
                    'status' => $this->paymentStatus($expected, $paid),
                ];
            })
            ->values();
    }

    private function expectedAmount(SchoolClass $schoolClass, ?AcademicYear $academicYear): ?float
    {
        $amount = FeeSchedule::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->sum('amount');

        return $amount > 0 ? (float) $amount : null;
    }

    private function paymentStatus(?float $expected, float $paid): array
    {
        if (is_null($expected)) {
            return ['key' => 'unconfigured', 'label' => 'Tarif à configurer', 'class' => 'badge-warning'];
        }

        if ($paid <= 0) {
            return ['key' => 'unpaid', 'label' => 'Impaye', 'class' => 'badge-warning'];
        }

        if ($paid < $expected) {
            return ['key' => 'partial', 'label' => 'Partiel', 'class' => 'badge-warning'];
        }

        return ['key' => 'paid', 'label' => 'A jour', 'class' => ''];
    }

    private function filterPaymentRows(Collection $rows, Request $request): Collection
    {
        $search = Str::lower(trim($request->string('search')->toString()));
        $status = $request->string('status')->toString();

        return $rows
            ->when($status, fn (Collection $items) => $items->filter(fn (array $row) => $row['status']['key'] === $status))
            ->when($search, function (Collection $items) use ($search) {
                return $items->filter(function (array $row) use ($search) {
                    $student = $row['student'];

                    return Str::contains(Str::lower($student?->full_name ?? ''), $search)
                        || Str::contains(Str::lower($student?->matricule ?? ''), $search);
                });
            })
            ->values();
    }

    private function studentPaymentContact($student): string
    {
        if (! $student) {
            return '';
        }

        $guardian = $student->guardians->first();

        return $guardian?->phone_primary
            ?? $guardian?->phone_secondary
            ?? $student->home_phone
            ?? $student->emergency_contact_phone
            ?? '';
    }

    private function paymentSummary(Collection $rows): array
    {
        $expectedTotal = $rows->contains(fn (array $row) => is_null($row['expected']))
            ? null
            : $rows->sum('expected');

        $paidTotal = $rows->sum('paid');

        return [
            'expected' => $expectedTotal,
            'paid' => $paidTotal,
            'balance' => is_null($expectedTotal) ? null : max($expectedTotal - $paidTotal, 0),
            'up_to_date' => $rows->filter(fn (array $row) => $row['status']['label'] === 'A jour')->count(),
            'partial' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Partiel')->count(),
            'unpaid' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Impaye')->count(),
        ];
    }

    private function installmentRows(?SchoolClass $schoolClass, ?AcademicYear $academicYear): Collection
    {
        if (! $schoolClass) {
            return collect();
        }

        $schedules = FeeSchedule::query()
            ->with('feeType')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->orderBy('period')
            ->orderBy('id')
            ->get();
        $studentIds = $schoolClass->enrollments->pluck('student_id')->all();

        $paidByStudentAndSchedule = PaymentLine::query()
            ->join('payments', 'payments.id', '=', 'payment_lines.payment_id')
            ->when($academicYear, fn ($query) => $query->where('payments.academic_year_id', $academicYear->id))
            ->where('payments.status', 'valid')
            ->whereIn('payments.student_id', $studentIds)
            ->whereNotNull('payment_lines.fee_schedule_id')
            ->selectRaw('payments.student_id, payment_lines.fee_schedule_id, sum(payment_lines.amount) as total_paid')
            ->groupBy('payments.student_id', 'payment_lines.fee_schedule_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->student_id.':'.$row->fee_schedule_id => (float) $row->total_paid]);

        return $schoolClass->enrollments
            ->flatMap(function ($enrollment) use ($paidByStudentAndSchedule, $schedules) {
                return $schedules->map(function (FeeSchedule $schedule) use ($enrollment, $paidByStudentAndSchedule) {
                    $expected = (float) $schedule->amount;
                    $paid = (float) ($paidByStudentAndSchedule[$enrollment->student_id.':'.$schedule->id] ?? 0);
                    $balance = max($expected - $paid, 0);

                    return [
                        'student' => $enrollment->student,
                        'schedule' => $schedule,
                        'expected' => $expected,
                        'paid' => $paid,
                        'balance' => $balance,
                        'status' => $this->paymentStatus($expected, $paid),
                    ];
                });
            })
            ->values();
    }

    private function missingDocumentRows(?SchoolClass $schoolClass, Collection $classes, ?AcademicYear $academicYear, RequiredStudentDocumentService $requiredDocuments): Collection
    {
        if (! $academicYear || $classes->isEmpty()) {
            return collect();
        }

        $classIds = $schoolClass ? [$schoolClass->id] : $classes->pluck('id')->all();
        $enrollments = Enrollment::query()
            ->with(['schoolClass.level', 'student.documents'])
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('school_class_id', $classIds)
            ->where('enrollments.status', 'active')
            ->whereHas('student', fn ($studentQuery) => $studentQuery->where('status', 'active'))
            ->get();

        return $requiredDocuments->reportRows($enrollments)
            ->sortBy(fn (array $row) => ($row['class']?->name ?? '').'|'.Str::lower($row['student']?->full_name ?? ''))
            ->values();
    }

    private function incompleteStudentRows(?SchoolClass $schoolClass, Collection $classes, ?AcademicYear $academicYear, RequiredStudentDocumentService $requiredDocuments): Collection
    {
        if (! $academicYear || $classes->isEmpty()) {
            return collect();
        }

        $classIds = $schoolClass ? [$schoolClass->id] : $classes->pluck('id')->all();
        $enrollments = Enrollment::query()
            ->with(['schoolClass.level', 'student.guardians', 'student.documents'])
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('school_class_id', $classIds)
            ->where('enrollments.status', 'active')
            ->whereHas('student', fn ($studentQuery) => $studentQuery->where('status', 'active'))
            ->get();

        return $enrollments
            ->map(function (Enrollment $enrollment) use ($requiredDocuments) {
                $student = $enrollment->student;
                $schoolClass = $enrollment->schoolClass;
                $missingDocuments = $student ? $requiredDocuments->missingForStudent($student, $schoolClass) : [];
                $hasContact = $this->studentHasContact($student);
                $hasPhoto = $this->studentHasPhoto($student);
                $issues = $this->studentDataIssues($student, $missingDocuments, $hasContact, $hasPhoto);

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'class' => $schoolClass,
                    'has_contact' => $hasContact,
                    'has_photo' => $hasPhoto,
                    'missing_documents' => $missingDocuments,
                    'issues' => $issues,
                    'is_complete' => count($issues) === 0,
                ];
            })
            ->sortBy(fn (array $row) => ($row['class']?->name ?? '').'|'.Str::lower($row['student']?->full_name ?? ''))
            ->values();
    }

    private function studentDataIssues($student, array $missingDocuments, bool $hasContact, bool $hasPhoto): array
    {
        if (! $student) {
            return [['key' => 'identity', 'label' => 'Élève introuvable']];
        }

        $issues = [];

        if (blank($student->gender)) {
            $issues[] = ['key' => 'gender', 'label' => 'Sexe non renseigné'];
        }

        if (blank($student->birth_date)) {
            $issues[] = ['key' => 'birth_date', 'label' => 'Date de naissance manquante'];
        }

        if (! $hasContact) {
            $issues[] = ['key' => 'contact', 'label' => 'Contact parent/tuteur manquant'];
        }

        if (! $hasPhoto) {
            $issues[] = ['key' => 'photo', 'label' => 'Photo manquante'];
        }

        foreach ($missingDocuments as $document) {
            if (($document['type'] ?? null) === 'photo') {
                continue;
            }

            $issues[] = [
                'key' => 'documents',
                'label' => 'Pièce manquante : '.$document['label'],
            ];
        }

        return $issues;
    }

    private function studentHasContact($student): bool
    {
        if (! $student) {
            return false;
        }

        if (filled($student->home_phone) || filled($student->emergency_contact_phone) || filled($student->school_info_whatsapp)) {
            return true;
        }

        return $student->guardians
            ->contains(fn ($guardian) => filled($guardian->phone_primary) || filled($guardian->phone_secondary));
    }

    private function studentHasPhoto($student): bool
    {
        if (! $student) {
            return false;
        }

        if (filled($student->photo_path)) {
            return true;
        }

        return $student->documents
            ->where('document_type', 'photo')
            ->where('status', 'received')
            ->isNotEmpty();
    }

    private function filterMissingDocumentRows(Collection $rows, Request $request): Collection
    {
        $search = Str::lower(trim($request->string('search')->toString()));
        $status = $request->string('status')->toString();

        return $rows
            ->when($status === 'complete', fn (Collection $items) => $items->where('is_complete', true))
            ->when($status === 'incomplete', fn (Collection $items) => $items->where('is_complete', false))
            ->when($search, function (Collection $items) use ($search) {
                return $items->filter(function (array $row) use ($search) {
                    $student = $row['student'];

                    return Str::contains(Str::lower($student?->full_name ?? ''), $search)
                        || Str::contains(Str::lower($student?->matricule ?? ''), $search);
                });
            })
            ->values();
    }

    private function filterIncompleteStudentRows(Collection $rows, Request $request): Collection
    {
        $search = Str::lower(trim($request->string('search')->toString()));
        $status = $request->string('status')->toString();
        $issue = $request->string('issue')->toString();

        return $rows
            ->when($status === 'complete', fn (Collection $items) => $items->where('is_complete', true))
            ->when($status === 'incomplete', fn (Collection $items) => $items->where('is_complete', false))
            ->when($issue, function (Collection $items) use ($issue) {
                return $items->filter(fn (array $row) => collect($row['issues'])->contains('key', $issue));
            })
            ->when($search, function (Collection $items) use ($search) {
                return $items->filter(function (array $row) use ($search) {
                    $student = $row['student'];

                    return Str::contains(Str::lower($student?->full_name ?? ''), $search)
                        || Str::contains(Str::lower($student?->matricule ?? ''), $search);
                });
            })
            ->values();
    }

    private function incompleteStudentSummary(Collection $rows): array
    {
        return [
            'students' => $rows->count(),
            'complete' => $rows->where('is_complete', true)->count(),
            'incomplete' => $rows->where('is_complete', false)->count(),
            'missing_gender' => $rows->filter(fn (array $row) => collect($row['issues'])->contains('key', 'gender'))->count(),
            'missing_birth_date' => $rows->filter(fn (array $row) => collect($row['issues'])->contains('key', 'birth_date'))->count(),
            'missing_contact' => $rows->filter(fn (array $row) => collect($row['issues'])->contains('key', 'contact'))->count(),
            'missing_photo' => $rows->filter(fn (array $row) => collect($row['issues'])->contains('key', 'photo'))->count(),
            'missing_documents' => $rows->filter(fn (array $row) => collect($row['issues'])->contains('key', 'documents'))->count(),
        ];
    }

    private function requiredDocumentLabelsForReport(?SchoolClass $schoolClass, RequiredStudentDocumentService $requiredDocuments): array
    {
        if (! $schoolClass) {
            return ['Variable selon la classe sélectionnée'];
        }

        return array_values($requiredDocuments->requiredTypesForClass($schoolClass));
    }

    private function installmentSummary(Collection $rows): array
    {
        return [
            'expected' => (float) $rows->sum('expected'),
            'paid' => (float) $rows->sum('paid'),
            'balance' => (float) $rows->sum('balance'),
            'up_to_date' => $rows->filter(fn (array $row) => $row['status']['label'] === 'A jour')->count(),
            'partial' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Partiel')->count(),
            'unpaid' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Impaye')->count(),
        ];
    }

    private function installmentStudentRows(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (array $row) => filled($row['student']?->id))
            ->groupBy(fn (array $row) => $row['student']->id)
            ->map(function (Collection $studentInstallments) {
                $student = $studentInstallments->first()['student'];
                $expected = (float) $studentInstallments->sum('expected');
                $paid = (float) $studentInstallments->sum('paid');
                $balance = (float) $studentInstallments->sum('balance');
                $unpaidRows = $studentInstallments
                    ->filter(fn (array $row) => (float) $row['balance'] > 0)
                    ->values();
                $status = $this->studentInstallmentStatus($expected, $paid, $balance);

                return [
                    'student' => $student,
                    'expected' => $expected,
                    'paid' => $paid,
                    'balance' => $balance,
                    'progress' => $expected > 0 ? min(round(($paid / $expected) * 100), 100) : 0,
                    'status' => $status,
                    'rows' => $studentInstallments->values(),
                    'unpaid_rows' => $unpaidRows,
                    'unpaid_count' => $unpaidRows->count(),
                ];
            })
            ->sortBy(function (array $row) {
                $order = ['unpaid' => 0, 'partial' => 1, 'paid' => 2];

                return ($order[$row['status']['key']] ?? 9).'|'.Str::lower($row['student']->full_name);
            })
            ->values();
    }

    private function filterInstallmentStudentRows(Collection $studentRows, Request $request): Collection
    {
        $search = Str::lower(trim($request->string('search')->toString()));
        $status = $request->string('status')->toString();

        return $studentRows
            ->when($status, fn (Collection $rows) => $rows->filter(fn (array $row) => $row['status']['key'] === $status))
            ->when($search, function (Collection $rows) use ($search) {
                return $rows->filter(function (array $row) use ($search) {
                    $student = $row['student'];

                    return Str::contains(Str::lower($student->full_name), $search)
                        || Str::contains(Str::lower($student->matricule ?? ''), $search);
                });
            })
            ->values();
    }

    private function installmentStudentSummary(Collection $studentRows): array
    {
        return [
            'total' => $studentRows->count(),
            'paid' => $studentRows->filter(fn (array $row) => $row['status']['key'] === 'paid')->count(),
            'partial' => $studentRows->filter(fn (array $row) => $row['status']['key'] === 'partial')->count(),
            'unpaid' => $studentRows->filter(fn (array $row) => $row['status']['key'] === 'unpaid')->count(),
        ];
    }

    private function studentInstallmentStatus(float $expected, float $paid, float $balance): array
    {
        if ($expected <= 0) {
            return ['key' => 'unpaid', 'label' => 'Tarif à configurer', 'class' => 'badge-warning'];
        }

        if ($balance <= 0) {
            return ['key' => 'paid', 'label' => 'A jour', 'class' => ''];
        }

        if ($paid > 0) {
            return ['key' => 'partial', 'label' => 'Partiel', 'class' => 'badge-warning'];
        }

        return ['key' => 'unpaid', 'label' => 'Impaye', 'class' => 'badge-danger'];
    }
}
