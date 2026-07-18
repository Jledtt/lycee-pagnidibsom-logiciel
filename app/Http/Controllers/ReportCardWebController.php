<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportCard\ReportCardSelectionRequest;
use App\Http\Requests\ReportCard\UpdateReportCardRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Services\GradeCalculationService;
use App\Services\ReportCardService;
use App\Services\XlsxExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportCardWebController extends Controller
{
    public function __construct(
        private readonly GradeCalculationService $gradeCalculationService,
        private readonly ReportCardService $reportCardService,
    ) {
    }

    public function index(Request $request): View
    {
        $academicYear = $this->requireActiveAcademicYear();

        $classes = SchoolClass::query()
            ->with('level')
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $terms = Term::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('position')
            ->get();

        $selectedClass = $classes->firstWhere('id', $request->integer('school_class_id')) ?? $classes->first();
        $selectedTerm = $terms->firstWhere('id', $request->integer('term_id')) ?? $terms->first();
        $students = collect();
        $reportCards = collect();

        if ($selectedClass && $selectedTerm) {
            $students = $this->studentsForClass($academicYear->id, $selectedClass->id);
            $reportCards = ReportCard::query()
                ->with('student')
                ->where('academic_year_id', $academicYear->id)
                ->where('school_class_id', $selectedClass->id)
                ->where('term_id', $selectedTerm->id)
                ->get()
                ->keyBy('student_id');
        }

        return view('report-cards.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'reportCards' => $reportCards,
            'selectedClass' => $selectedClass,
            'selectedTerm' => $selectedTerm,
            'students' => $students,
            'terms' => $terms,
        ]);
    }

    public function generate(ReportCardSelectionRequest $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $request->validated();

        $schoolClass = SchoolClass::query()->findOrFail($data['school_class_id']);
        $term = Term::query()->findOrFail($data['term_id']);
        $rows = $this->reportCardService->generateForClass($schoolClass, $term);

        return redirect()
            ->route('report-cards.index', [
                'school_class_id' => $schoolClass->id,
                'term_id' => $term->id,
            ])
            ->with('success', count($rows) . ' bulletin(s) genere(s).');
    }

    public function pdf(ReportCard $reportCard)
    {
        $reportCard->load(['academicYear', 'term', 'student', 'schoolClass.level']);

        $subjectRows = $this->subjectRows($reportCard);
        $filename = 'bulletin-' . Str::slug($reportCard->student->matricule . '-' . $reportCard->term->name) . '.pdf';

        return Pdf::loadView('report-cards.pdf', [
            'reportCard' => $reportCard,
            'school' => SchoolSetting::query()->first(),
            'subjectRows' => $subjectRows,
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function classPdf(ReportCardSelectionRequest $request)
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $request->validated();

        $schoolClass = SchoolClass::query()->with('level')->findOrFail($data['school_class_id']);
        $term = Term::query()->findOrFail($data['term_id']);

        $this->reportCardService->generateForClass($schoolClass, $term);

        $reportCards = ReportCard::query()
            ->with(['academicYear', 'term', 'student', 'schoolClass.level'])
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->join('students', 'students.id', '=', 'report_cards.student_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('report_cards.*')
            ->get()
            ->map(fn (ReportCard $reportCard) => [
                'reportCard' => $reportCard,
                'subjectRows' => $this->subjectRows($reportCard),
            ]);

        $filename = 'bulletins-' . Str::slug($schoolClass->name . '-' . $term->name) . '.pdf';

        return Pdf::loadView('report-cards.class-pdf', [
            'items' => $reportCards,
            'school' => SchoolSetting::query()->first(),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function classExport(ReportCardSelectionRequest $request, XlsxExportService $xlsxExport)
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $request->validated();
        $schoolClass = SchoolClass::query()->with('level')->findOrFail($data['school_class_id']);
        $term = Term::query()->findOrFail($data['term_id']);

        $this->reportCardService->generateForClass($schoolClass, $term);

        $reportCards = ReportCard::query()
            ->with(['student', 'schoolClass', 'term'])
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->join('students', 'students.id', '=', 'report_cards.student_id')
            ->orderBy('report_cards.rank')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('report_cards.*')
            ->get();

        return $xlsxExport->download('bulletins-' . Str::slug($schoolClass->name . '-' . $term->name) . '.xlsx', [
            'Rang',
            'Matricule',
            'Eleve',
            'Classe',
            'Trimestre',
            'Moyenne',
            'Appreciation',
            'Decision',
            'Statut',
        ], $reportCards->map(fn (ReportCard $reportCard) => [
            $reportCard->rank,
            $reportCard->student?->matricule,
            $reportCard->student?->full_name,
            $schoolClass->name,
            $term->name,
            $reportCard->general_average,
            $reportCard->appreciation,
            $reportCard->decision,
            $reportCard->status,
        ]));
    }

    public function update(UpdateReportCardRequest $request, ReportCard $reportCard): RedirectResponse
    {
        $data = $request->validated();

        $payload = [
            'appreciation' => $this->reportCardService->appreciationForAverage(
                $reportCard->general_average === null ? null : (float) $reportCard->general_average,
            ),
            'decision' => $data['decision'] ?: $this->reportCardService->decisionForAverage(
                $reportCard->general_average === null ? null : (float) $reportCard->general_average,
            ),
            'principal_observation' => $data['principal_observation'] ?? null,
            'status' => $data['status'],
        ];

        if (in_array($data['status'], ['validated', 'published'], true)) {
            $payload['validated_by'] = $reportCard->validated_by ?: $request->user()->id;
            $payload['validated_at'] = $reportCard->validated_at ?: now();
        }

        if ($data['status'] === 'draft') {
            $payload['validated_by'] = null;
            $payload['validated_at'] = null;
        }

        $reportCard->update($payload);

        return redirect()
            ->route('report-cards.index', [
                'school_class_id' => $reportCard->school_class_id,
                'term_id' => $reportCard->term_id,
            ])
            ->with('success', 'Bulletin mis a jour.');
    }

    private function subjectRows(ReportCard $reportCard): Collection
    {
        return ClassSubject::query()
            ->with('subject')
            ->where('school_class_id', $reportCard->school_class_id)
            ->where('is_active', true)
            ->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
            ->orderBy('subjects.name')
            ->select('class_subjects.*')
            ->get()
            ->map(function (ClassSubject $classSubject) use ($reportCard) {
                $average = $this->gradeCalculationService->subjectAverage(
                    $reportCard->student,
                    $reportCard->term,
                    $classSubject->subject_id,
                    $reportCard->school_class_id,
                );

                $coefficient = (float) $classSubject->coefficient;

                return [
                    'subject' => $classSubject->subject,
                    'coefficient' => $coefficient,
                    'average' => $average,
                    'points' => $average === null ? null : round($average * $coefficient, 2),
                    'appreciation' => $this->appreciation($average),
                ];
            });
    }

    private function appreciation(?float $average): string
    {
        if ($average === null) {
            return 'Non note';
        }

        return match (true) {
            $average >= 16 => 'Excellent',
            $average >= 14 => 'Tres bien',
            $average >= 12 => 'Bien',
            $average >= 10 => 'Passable',
            $average >= 8 => 'Insuffisant',
            default => 'Tres insuffisant',
        };
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        abort_if(! $academicYear, 422, 'Aucune annee scolaire active.');

        return $academicYear;
    }

    private function studentsForClass(int $academicYearId, int $schoolClassId): Collection
    {
        return Enrollment::query()
            ->with('student')
            ->where('academic_year_id', $academicYearId)
            ->where('school_class_id', $schoolClassId)
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
}
