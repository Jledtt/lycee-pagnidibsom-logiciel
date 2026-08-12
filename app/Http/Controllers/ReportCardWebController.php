<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportCard\ReportCardSelectionRequest;
use App\Http\Requests\ReportCard\UpdateReportCardRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Models\TermPeriod;
use App\Services\CompetitionRankingService;
use App\Services\GradeCalculationService;
use App\Services\ReportCardService;
use App\Services\TermPeriodService;
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
        private readonly TermPeriodService $termPeriodService,
        private readonly CompetitionRankingService $competitionRankingService,
    ) {}

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
        $termPeriods = $selectedTerm ? $this->termPeriodService->ensureDefaults($selectedTerm) : collect();
        $selectedTermPeriod = $termPeriods->firstWhere('id', $request->integer('term_period_id')) ?? $termPeriods->first();
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
            'selectedTermPeriod' => $selectedTermPeriod,
            'students' => $students,
            'termPeriods' => $termPeriods,
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
            ->with('success', count($rows).' bulletin(s) généré(s).');
    }

    public function pdf(ReportCard $reportCard)
    {
        $reportCard->load(['academicYear', 'term', 'student', 'schoolClass.level']);

        $subjectRows = $this->subjectRows($reportCard);
        $filename = 'bulletin-'.Str::slug($reportCard->student->matricule.'-'.$reportCard->term->name).'.pdf';
        $annualSummary = $this->reportCardService->termPosition($reportCard->term) >= 3
            ? $this->reportCardService->annualSummariesForClass($reportCard->schoolClass)->get($reportCard->student_id)
            : null;

        return Pdf::loadView('report-cards.pdf', [
            'annualSummary' => $annualSummary,
            'classStats' => $this->classStats($reportCard),
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
        $annualSummaries = $this->reportCardService->termPosition($term) >= 3
            ? $this->reportCardService->annualSummariesForClass($schoolClass)
            : collect();

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
                'annualSummary' => $annualSummaries->get($reportCard->student_id),
                'classStats' => $this->classStats($reportCard),
                'reportCard' => $reportCard,
                'subjectRows' => $this->subjectRows($reportCard),
            ]);

        $filename = 'bulletins-'.Str::slug($schoolClass->name.'-'.$term->name).'.pdf';

        return Pdf::loadView('report-cards.class-pdf', [
            'items' => $reportCards,
            'school' => SchoolSetting::query()->first(),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function periodClassPdf(ReportCardSelectionRequest $request)
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'term_period_id' => ['required', 'exists:term_periods,id'],
        ]);

        $schoolClass = SchoolClass::query()->with('level')->findOrFail($data['school_class_id']);
        $term = Term::query()->findOrFail($data['term_id']);
        $period = TermPeriod::query()->findOrFail($data['term_period_id']);
        abort_unless((int) $period->term_id === (int) $term->id, 422, 'Cette période ne correspond pas au trimestre sélectionné.');

        $students = $this->studentsForClass($schoolClass->academic_year_id, $schoolClass->id);
        $unrankedRows = $students
            ->map(function ($student) use ($schoolClass, $term, $period) {
                $average = $this->gradeCalculationService->generalAverage($student, $schoolClass, $term, $period->id);

                return [
                    'student' => $student,
                    'average' => $average,
                    'appreciation' => $this->appreciation($average),
                    'subjectRows' => $this->subjectRowsForStudent($student, $schoolClass, $term, $period->id),
                ];
            })
            ->values()
            ->all();
        $rows = collect($this->competitionRankingService->rank($unrankedRows))
            ->map(function (array $row) use ($students) {
                $row['classSize'] = $students->count();

                return $row;
            });
        $ratedAverages = $rows
            ->pluck('average')
            ->filter(fn ($average) => $average !== null)
            ->map(fn ($average) => (float) $average);
        $classStats = [
            'average' => $ratedAverages->isEmpty() ? null : round($ratedAverages->avg(), 2),
            'best' => $ratedAverages->isEmpty() ? null : $ratedAverages->max(),
            'weakest' => $ratedAverages->isEmpty() ? null : $ratedAverages->min(),
        ];

        $filename = 'releves-'.Str::slug($schoolClass->name.'-'.$term->name.'-'.$period->name).'.pdf';

        return Pdf::loadView('report-cards.period-class-pdf', [
            'classStats' => $classStats,
            'items' => $rows,
            'period' => $period,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'term' => $term,
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

        return $xlsxExport->download('bulletins-'.Str::slug($schoolClass->name.'-'.$term->name).'.xlsx', [
            'Rang',
            'Matricule',
            'Élève',
            'Classe',
            'Trimestre',
            'Moyenne',
            'Appreciation',
            'Décision',
            'Statut',
        ], $reportCards->map(fn (ReportCard $reportCard) => [
            $reportCard->rank_label,
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
            'conduct' => $data['conduct'] ?: null,
            'distinction' => $data['distinction'] ?: null,
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
            ->with('success', 'Bulletin mis à jour.');
    }

    private function subjectRows(ReportCard $reportCard): Collection
    {
        return $this->gradeCalculationService
            ->termSummary($reportCard->student, $reportCard->schoolClass, $reportCard->term)['rows']
            ->sortBy(fn (array $row): string => $row['class_subject']->subject->name)
            ->map(function (array $row) {
                $classSubject = $row['class_subject'];

                return [
                    'subject' => $classSubject->subject,
                    'coefficient' => $row['coefficient'],
                    'devoir_average' => $row['devoir'],
                    'composition_average' => $row['composition'],
                    'average' => $row['general'],
                    'points' => $row['points'],
                    'appreciation' => $this->appreciation($row['general']),
                    'teacher' => $classSubject->teacher?->name ?? '-',
                ];
            })
            ->values();
    }

    private function subjectRowsForStudent($student, SchoolClass $schoolClass, Term $term, ?int $termPeriodId = null): Collection
    {
        return $this->gradeCalculationService
            ->termSummary($student, $schoolClass, $term, $termPeriodId)['rows']
            ->sortBy(fn (array $row): string => $row['class_subject']->subject->name)
            ->map(function (array $row) {
                $classSubject = $row['class_subject'];

                return [
                    'subject' => $classSubject->subject,
                    'coefficient' => $row['coefficient'],
                    'average' => $row['general'],
                    'points' => $row['points'],
                    'appreciation' => $this->appreciation($row['general']),
                    'teacher' => $classSubject->teacher?->name ?? '-',
                ];
            })
            ->values();
    }

    private function classStats(ReportCard $reportCard): array
    {
        $cards = ReportCard::query()
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->where('term_id', $reportCard->term_id)
            ->where('school_class_id', $reportCard->school_class_id)
            ->whereNotNull('general_average')
            ->get();
        $extremes = $this->reportCardService->classExtremes($reportCard->term, $reportCard->schoolClass);

        return [
            'average' => $cards->isEmpty() ? null : round($cards->avg(fn (ReportCard $card) => (float) $card->general_average), 2),
            'best' => $extremes['highest'],
            'weakest' => $extremes['lowest'],
        ];
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

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

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
