<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Term;
use App\Services\CompetitionRankingService;
use App\Services\GradeCalculationService;
use App\Services\ReportCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassCouncilWebController extends Controller
{
    public function __construct(
        private readonly GradeCalculationService $gradeCalculationService,
        private readonly ReportCardService $reportCardService,
        private readonly CompetitionRankingService $competitionRankingService,
    ) {}

    public function index(Request $request): View
    {
        [$academicYear, $classes, $terms, $selectedClass, $selectedTerm] = $this->selection($request);
        $reportCards = collect();
        $summary = $this->emptySummary();
        $lockSummary = $this->emptyLockSummary();

        if ($selectedClass && $selectedTerm) {
            $reportCards = $this->reportCards($academicYear, $selectedClass, $selectedTerm);
            $summary = $this->summary($reportCards);
            $lockSummary = $this->lockSummary($selectedClass, $selectedTerm);
        }

        return view('class-council.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id', 'term_id']),
            'lockSummary' => $lockSummary,
            'reportCards' => $reportCards,
            'selectedClass' => $selectedClass,
            'selectedTerm' => $selectedTerm,
            'summary' => $summary,
            'terms' => $terms,
        ]);
    }

    public function pvPdf(Request $request)
    {
        [$academicYear, , , $schoolClass, $term] = $this->selection($request);

        abort_if(! $schoolClass || ! $term, 404, 'Classe ou trimestre introuvable.');

        $this->reportCardService->generateForClass($schoolClass, $term);

        $reportCards = $this->reportCards($academicYear, $schoolClass, $term);
        $filename = 'pv-conseil-'.Str::slug($schoolClass->name.'-'.$term->name).'.pdf';

        return Pdf::loadView('class-council.pv-pdf', [
            'academicYear' => $academicYear,
            'lockSummary' => $this->lockSummary($schoolClass, $term),
            'reportCards' => $reportCards,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $this->summary($reportCards),
            'term' => $term,
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function transcriptPdf(ReportCard $reportCard)
    {
        $reportCard->load(['academicYear', 'term', 'student', 'schoolClass.level']);
        $filename = 'releve-notes-'.Str::slug($reportCard->student->matricule.'-'.$reportCard->term->name).'.pdf';

        return Pdf::loadView('class-council.transcript-pdf', [
            'assessmentRows' => $this->assessmentRows($reportCard),
            'reportCard' => $reportCard,
            'school' => SchoolSetting::query()->first(),
            'subjectRows' => $this->subjectRows($reportCard),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function annualRedemptions(Request $request): View
    {
        [$academicYear, $classes, $terms, $selectedClass] = $this->annualSelection($request);
        $threshold = $this->redemptionThreshold($request);
        $rows = $selectedClass ? $this->annualRedemptionRows($academicYear, $selectedClass, $terms, $threshold) : collect();

        return view('class-council.annual-redemptions', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'eligibleRows' => $rows->where('is_eligible', true)->values(),
            'rows' => $rows,
            'selectedClass' => $selectedClass,
            'terms' => $terms,
            'threshold' => $threshold,
        ]);
    }

    public function annualRedemptionsPdf(Request $request)
    {
        [$academicYear, $classes, $terms, $selectedClass] = $this->annualSelection($request);
        $threshold = $this->redemptionThreshold($request);

        abort_if(! $selectedClass, 404, 'Classe introuvable.');

        $eligibleRows = $this->annualRedemptionRows($academicYear, $selectedClass, $terms, $threshold)
            ->where('is_eligible', true)
            ->values();

        return Pdf::loadView('class-council.annual-redemptions-pdf', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'eligibleRows' => $eligibleRows,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $selectedClass,
            'terms' => $terms,
            'threshold' => $threshold,
        ])
            ->setPaper('a4')
            ->stream('liste-rachats-'.Str::slug($selectedClass->name.'-'.$academicYear->name).'.pdf');
    }

    public function lock(Request $request): RedirectResponse
    {
        [$academicYear, , , $schoolClass, $term] = $this->validatedSelection($request);

        $this->reportCardService->generateForClass($schoolClass, $term);

        Assessment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->update(['is_locked' => true]);

        ReportCard::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->update([
                'status' => 'validated',
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ]);

        return redirect()
            ->route('class-council.index', [
                'school_class_id' => $schoolClass->id,
                'term_id' => $term->id,
            ])
            ->with('success', 'Conseil verrouillé. Les notes du trimestre sont protégées.');
    }

    public function unlock(Request $request): RedirectResponse
    {
        [$academicYear, , , $schoolClass, $term] = $this->validatedSelection($request);

        Assessment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->update(['is_locked' => false]);

        return redirect()
            ->route('class-council.index', [
                'school_class_id' => $schoolClass->id,
                'term_id' => $term->id,
            ])
            ->with('success', 'Conseil déverrouillé pour correction admin.');
    }

    private function selection(Request $request): array
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

        return [$academicYear, $classes, $terms, $selectedClass, $selectedTerm];
    }

    private function annualSelection(Request $request): array
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

        return [$academicYear, $classes, $terms, $selectedClass];
    }

    private function validatedSelection(Request $request): array
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('academic_year_id', $academicYear->id),
            ],
            'term_id' => [
                'required',
                Rule::exists('terms', 'id')->where('academic_year_id', $academicYear->id),
            ],
        ]);

        return [
            $academicYear,
            collect(),
            collect(),
            SchoolClass::query()->with('level')->findOrFail($data['school_class_id']),
            Term::query()->findOrFail($data['term_id']),
        ];
    }

    private function reportCards(AcademicYear $academicYear, SchoolClass $schoolClass, Term $term): Collection
    {
        return ReportCard::query()
            ->with('student')
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->orderByRaw('case when report_cards.rank is null then 999999 else report_cards.rank end')
            ->join('students', 'students.id', '=', 'report_cards.student_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('report_cards.*')
            ->get();
    }

    private function redemptionThreshold(Request $request): float
    {
        $threshold = (float) str_replace(',', '.', (string) $request->input('threshold', '9.85'));

        return min(9.99, max(0, round($threshold, 2)));
    }

    private function annualRedemptionRows(AcademicYear $academicYear, SchoolClass $schoolClass, Collection $terms, float $threshold): Collection
    {
        $termIds = $terms->pluck('id');
        $cardsByStudent = ReportCard::query()
            ->with('student')
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->whereIn('term_id', $termIds)
            ->whereNotNull('general_average')
            ->get()
            ->groupBy('student_id');

        $students = Enrollment::query()
            ->with('student')
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('enrollments.status', 'active')
            ->whereHas('student', fn ($query) => $query->where('students.status', 'active'))
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('enrollments.*')
            ->get()
            ->pluck('student');

        $rows = $students
            ->map(function (Student $student) use ($cardsByStudent, $terms, $threshold) {
                $cards = $cardsByStudent->get($student->id, collect())->keyBy('term_id');
                $averages = $terms->mapWithKeys(fn (Term $term) => [
                    $term->id => $cards->get($term->id)?->general_average === null ? null : (float) $cards->get($term->id)->general_average,
                ]);
                $knownAverages = $averages->filter(fn ($average) => $average !== null);
                $annualAverage = $knownAverages->isEmpty() ? null : round($knownAverages->avg(), 2);

                return [
                    'annual_average' => $annualAverage,
                    'is_eligible' => $annualAverage !== null && $annualAverage >= $threshold && $annualAverage < 10,
                    'redeemed_average' => $annualAverage !== null && $annualAverage >= $threshold && $annualAverage < 10 ? 10.00 : $annualAverage,
                    'student' => $student,
                    'term_averages' => $averages,
                    'terms_count' => $knownAverages->count(),
                ];
            })
            ->values()
            ->all();

        return collect($this->competitionRankingService->rank($rows, 'annual_average'));
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
                    'appreciation' => $this->reportCardService->appreciationForAverage($average),
                ];
            });
    }

    private function assessmentRows(ReportCard $reportCard): Collection
    {
        $assessments = Assessment::query()
            ->with(['subject', 'assessmentType'])
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->where('school_class_id', $reportCard->school_class_id)
            ->where('term_id', $reportCard->term_id)
            ->join('subjects', 'subjects.id', '=', 'assessments.subject_id')
            ->orderBy('subjects.name')
            ->orderBy('assessments.assessment_date')
            ->select('assessments.*')
            ->get();

        $grades = Grade::query()
            ->where('student_id', $reportCard->student_id)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy('assessment_id');

        return $assessments->map(function (Assessment $assessment) use ($grades) {
            $grade = $grades->get($assessment->id);
            $normalizedScore = $grade?->score === null
                ? null
                : round(((float) $grade->score / (float) $assessment->max_score) * 20, 2);

            return [
                'assessment' => $assessment,
                'grade' => $grade,
                'normalized_score' => $normalizedScore,
                'appreciation' => $this->reportCardService->appreciationForAverage($normalizedScore),
            ];
        });
    }

    private function summary(Collection $reportCards): array
    {
        $graded = $reportCards->filter(fn (ReportCard $card) => $card->general_average !== null);
        $best = $graded->sortByDesc(fn (ReportCard $card) => (float) $card->general_average)->first();
        $weakest = $graded->sortBy(fn (ReportCard $card) => (float) $card->general_average)->first();

        return [
            'students' => $reportCards->count(),
            'graded' => $graded->count(),
            'class_average' => $graded->isEmpty() ? null : round($graded->avg(fn (ReportCard $card) => (float) $card->general_average), 2),
            'best' => $best,
            'weakest' => $weakest,
            'admitted' => $reportCards->filter(fn (ReportCard $card) => Str::contains(Str::lower($card->decision ?? ''), 'admis'))->count(),
            'deliberation' => $reportCards->filter(fn (ReportCard $card) => Str::contains(Str::lower($card->decision ?? ''), 'deliberer'))->count(),
            'validated' => $reportCards->whereIn('status', ['validated', 'published'])->count(),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'students' => 0,
            'graded' => 0,
            'class_average' => null,
            'best' => null,
            'weakest' => null,
            'admitted' => 0,
            'deliberation' => 0,
            'validated' => 0,
        ];
    }

    private function lockSummary(SchoolClass $schoolClass, Term $term): array
    {
        $total = Assessment::query()
            ->where('academic_year_id', $schoolClass->academic_year_id)
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->count();

        $locked = Assessment::query()
            ->where('academic_year_id', $schoolClass->academic_year_id)
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->where('is_locked', true)
            ->count();

        return [
            'total' => $total,
            'locked' => $locked,
            'is_locked' => $total > 0 && $locked === $total,
            'is_partial' => $locked > 0 && $locked < $total,
        ];
    }

    private function emptyLockSummary(): array
    {
        return [
            'total' => 0,
            'locked' => 0,
            'is_locked' => false,
            'is_partial' => false,
        ];
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
    }
}
