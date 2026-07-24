<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Services\ExportCenterService;
use App\Services\XlsxExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportCenterWebController extends Controller
{
    public function __construct(
        private ExportCenterService $exports,
        private XlsxExportService $xlsx,
    ) {
    }

    public function index(Request $request)
    {
        $academicYears = AcademicYear::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();
        $selectedYear = $this->selectedAcademicYear($request) ?? $academicYears->first();
        $classes = $selectedYear ? $this->exports->classesFor($selectedYear) : collect();
        $selectedClass = $this->selectedClass($request, $selectedYear);

        return view('exports.index', [
            'academicYears' => $academicYears,
            'selectedYear' => $selectedYear,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'terms' => $selectedYear ? $this->exports->termsFor($selectedYear) : collect(),
            'periods' => $selectedYear ? $this->exports->periodsFor($selectedYear) : collect(),
            'subjects' => $this->exports->subjects(),
            'mockExams' => $selectedYear ? $this->exports->mockExamsFor($selectedYear) : collect(),
        ]);
    }

    public function students(Request $request): Response
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'status' => ['nullable', 'in:active,inactive,archived'],
        ]);

        $year = $this->selectedAcademicYear($request);
        $class = $this->selectedClass($request, $year);

        return $this->xlsx->download(
            $this->exports->filename('eleves-par-classe', $year, $class),
            ['Matricule', 'Nom', 'Prenom', 'Sexe', 'Date naissance', 'Lieu naissance', 'Classe', 'Telephone domicile', 'Tuteur', 'Contact tuteur', 'Statut'],
            $this->exports->studentRows($year, $class?->id, $request->string('status')->toString() ?: null),
            'Eleves'
        );
    }

    public function payments(Request $request): Response
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'status' => ['nullable', 'in:valid,cancelled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $year = $this->selectedAcademicYear($request);
        $class = $this->selectedClass($request, $year);

        return $this->xlsx->download(
            $this->exports->filename('paiements', $year, $class),
            ['Recu', 'Date', 'Eleve', 'Matricule', 'Classe', 'Montant FCFA', 'Mode', 'Statut', 'Recu par', 'Notes'],
            $this->exports->paymentRows(
                $year,
                $class?->id,
                $request->string('status')->toString() ?: null,
                $request->string('date_from')->toString() ?: null,
                $request->string('date_to')->toString() ?: null,
            ),
            'Paiements'
        );
    }

    public function unpaid(Request $request): Response
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'minimum_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $year = $this->selectedAcademicYear($request);
        $class = $this->selectedClass($request, $year);

        return $this->xlsx->download(
            $this->exports->filename('impayes', $year, $class),
            ['Eleve', 'Matricule', 'Classe', 'Total attendu FCFA', 'Total paye FCFA', 'Reste FCFA', 'Tuteur', 'Contact'],
            $this->exports->unpaidRows($year, $class?->id, $request->filled('minimum_balance') ? (float) $request->input('minimum_balance') : null),
            'Impayes'
        );
    }

    public function grades(Request $request): Response
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'term_period_id' => ['nullable', 'integer', 'exists:term_periods,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ]);

        $year = $this->selectedAcademicYear($request);
        $class = $this->selectedClass($request, $year);

        return $this->xlsx->download(
            $this->exports->filename('notes', $year, $class),
            ['Classe', 'Periode', 'Evaluation', 'Matiere', 'Matricule', 'Eleve', 'Note', 'Note sur', 'Statut', 'Observation'],
            $this->exports->gradeRows(
                $year,
                $class?->id,
                $request->integer('term_id') ?: null,
                $request->integer('term_period_id') ?: null,
                $request->integer('subject_id') ?: null,
            ),
            'Notes'
        );
    }

    public function attendance(Request $request): Response
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'status' => ['nullable', 'in:present,absent,late'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $year = $this->selectedAcademicYear($request);
        $class = $this->selectedClass($request, $year);

        return $this->xlsx->download(
            $this->exports->filename('absences', $year, $class),
            ['Date', 'Classe', 'Matricule', 'Eleve', 'Statut', 'Retard minutes', 'Motif', 'Justifie le'],
            $this->exports->attendanceRows(
                $year,
                $class?->id,
                $request->string('status')->toString() ?: null,
                $request->string('date_from')->toString() ?: null,
                $request->string('date_to')->toString() ?: null,
            ),
            'Absences'
        );
    }

    public function mockExams(Request $request): Response
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'mock_exam_id' => ['nullable', 'integer', 'exists:mock_exams,id'],
            'status' => ['nullable', 'in:present,absent,withdrawn'],
        ]);

        $year = $this->selectedAcademicYear($request);
        $class = $this->selectedClass($request, $year);

        return $this->xlsx->download(
            $this->exports->filename('resultats-examen-blanc', $year, $class),
            ['Examen', 'Classe', 'Anonymat', 'Matricule', 'Eleve', 'Salle', 'Moyenne', 'Statut', 'Decision jury', 'Observation'],
            $this->exports->mockExamResultRows(
                $year,
                $request->integer('mock_exam_id') ?: null,
                $class?->id,
                $request->string('status')->toString() ?: null,
            ),
            'Examens blancs'
        );
    }

    public function teacherFees(Request $request): Response
    {
        $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'mock_exam_id' => ['nullable', 'integer', 'exists:mock_exams,id'],
            'fee_status' => ['nullable', 'in:pending,paid,cancelled'],
        ]);

        $year = $this->selectedAcademicYear($request);

        return $this->xlsx->download(
            $this->exports->filename('honoraires-professeurs', $year),
            ['Examen', 'Matiere', 'Type', 'Professeur', 'Taux FCFA', 'Montant FCFA', 'Statut', 'Paye le', 'Reference'],
            $this->exports->teacherFeeRows(
                $year,
                $request->integer('mock_exam_id') ?: null,
                $request->string('fee_status')->toString() ?: null,
            ),
            'Honoraires'
        );
    }

    private function selectedAcademicYear(Request $request): AcademicYear
    {
        if ($request->filled('academic_year_id')) {
            return AcademicYear::query()->findOrFail($request->integer('academic_year_id'));
        }

        return $this->exports->activeAcademicYear() ?? AcademicYear::query()->create([
            'name' => (string) now()->year,
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    private function selectedClass(Request $request, ?AcademicYear $academicYear): ?SchoolClass
    {
        if (! $academicYear || ! $request->filled('school_class_id')) {
            return null;
        }

        return SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->find($request->integer('school_class_id'));
    }
}
