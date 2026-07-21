<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\MockExam;
use App\Models\MockExamCandidate;
use App\Models\SchoolSetting;
use App\Services\MockExamService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MockExamWebController extends Controller
{
    public function __construct(
        private readonly MockExamService $mockExamService,
    ) {
    }

    public function index(Request $request): View
    {
        $academicYear = $this->requireActiveAcademicYear();
        $classes = $this->mockExamService->eligibleClasses($academicYear);

        $exams = MockExam::query()
            ->with(['classes.level'])
            ->withCount(['candidates', 'subjects'])
            ->where('academic_year_id', $academicYear->id)
            ->latest('id')
            ->get();

        $selectedExam = $exams->firstWhere('id', $request->integer('mock_exam_id')) ?? $exams->first();

        if ($selectedExam) {
            $selectedExam->load([
                'classes.level',
                'subjects.subject',
                'candidates.student',
                'candidates.schoolClass',
            ]);
        }

        $suggestedClassIds = $classes
            ->filter(fn ($class) => Str::contains(Str::lower($class->name.' '.$class->level?->name), ['3e', '3eme', 'troisieme', 'terminale', 'tle']))
            ->pluck('id')
            ->all();

        return view('mock-exams.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'exams' => $exams,
            'selectedExam' => $selectedExam,
            'suggestedClassIds' => $suggestedClassIds,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'exam_type' => ['required', 'in:bepc_blanc,bac_blanc'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'school_class_ids' => ['required', 'array', 'min:1'],
            'school_class_ids.*' => ['integer', 'exists:school_classes,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $exam = $this->mockExamService->createExam($academicYear, $data);

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $exam->id])
            ->with('success', 'Session d examen blanc creee avec candidats et matieres de base.');
    }

    public function syncCandidates(MockExam $mockExam): RedirectResponse
    {
        $count = $this->mockExamService->syncCandidates($mockExam);

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExam->id])
            ->with('success', $count . ' candidat(s) synchronise(s).');
    }

    public function generateAnonymousCodes(Request $request, MockExam $mockExam): RedirectResponse
    {
        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:8'],
        ]);

        $count = $this->mockExamService->generateAnonymousCodes($mockExam, $data['prefix'] ?: 'X');

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExam->id])
            ->with('success', $count . ' anonymat(s) genere(s).');
    }

    public function distributeRooms(Request $request, MockExam $mockExam): RedirectResponse
    {
        $data = $request->validate([
            'room_count' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $count = $this->mockExamService->distributeRooms($mockExam, (int) $data['room_count']);

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExam->id])
            ->with('success', $count . ' candidat(s) reparti(s) en salle.');
    }

    public function candidatesPdf(MockExam $mockExam)
    {
        $mockExam->load(['academicYear', 'classes.level', 'candidates.student', 'candidates.schoolClass']);

        return Pdf::loadView('mock-exams.candidates-pdf', [
            'exam' => $mockExam,
            'school' => SchoolSetting::query()->first(),
            'title' => 'Liste des candidats',
        ])
            ->setPaper('a4')
            ->stream('candidats-' . Str::slug($mockExam->name) . '.pdf');
    }

    public function roomsPdf(MockExam $mockExam)
    {
        $mockExam->load(['academicYear', 'classes.level', 'candidates.student', 'candidates.schoolClass']);

        return Pdf::loadView('mock-exams.rooms-pdf', [
            'exam' => $mockExam,
            'school' => SchoolSetting::query()->first(),
            'title' => 'Repartition par salle',
        ])
            ->setPaper('a4')
            ->stream('repartition-salles-' . Str::slug($mockExam->name) . '.pdf');
    }

    public function anonymityPdf(MockExam $mockExam)
    {
        $mockExam->load(['academicYear', 'classes.level', 'candidates.student', 'candidates.schoolClass']);

        return Pdf::loadView('mock-exams.anonymity-pdf', [
            'exam' => $mockExam,
            'school' => SchoolSetting::query()->first(),
            'title' => 'Liste des anonymats',
        ])
            ->setPaper('a4')
            ->stream('anonymats-' . Str::slug($mockExam->name) . '.pdf');
    }

    public function surveillancePvPdf(MockExam $mockExam)
    {
        $mockExam->load(['academicYear', 'classes.level', 'candidates.student', 'candidates.schoolClass', 'subjects.subject']);

        return Pdf::loadView('mock-exams.surveillance-pv-pdf', [
            'exam' => $mockExam,
            'school' => SchoolSetting::query()->first(),
            'title' => 'PV de surveillance',
        ])
            ->setPaper('a4')
            ->stream('pv-surveillance-' . Str::slug($mockExam->name) . '.pdf');
    }

    public function copyReceiptPdf(MockExam $mockExam)
    {
        $mockExam->load(['academicYear', 'classes.level', 'candidates.student', 'candidates.schoolClass', 'subjects.subject']);

        return Pdf::loadView('mock-exams.copy-receipt-pdf', [
            'exam' => $mockExam,
            'school' => SchoolSetting::query()->first(),
            'title' => 'Bordereau de reception des copies',
        ])
            ->setPaper('a4')
            ->stream('bordereau-copies-' . Str::slug($mockExam->name) . '.pdf');
    }

    public function resultsPdf(MockExam $mockExam, string $status = 'provisoire')
    {
        abort_unless(in_array($status, ['provisoire', 'definitif'], true), 404);

        $mockExam->load([
            'academicYear',
            'classes.level',
            'subjects.subject',
            'subjects.scores',
            'candidates.student',
            'candidates.schoolClass',
            'candidates.scores.subject',
        ]);

        $title = $status === 'definitif' ? 'Resultats definitifs' : 'Resultats provisoires';

        return Pdf::loadView('mock-exams.results-pdf', [
            'exam' => $mockExam,
            'results' => $this->resultRows($mockExam),
            'school' => SchoolSetting::query()->first(),
            'status' => $status,
            'title' => $title,
        ])
            ->setPaper('a4')
            ->stream(Str::slug($title . '-' . $mockExam->name) . '.pdf');
    }

    public function juryDecisionPdf(MockExam $mockExam)
    {
        $mockExam->load([
            'academicYear',
            'classes.level',
            'subjects.subject',
            'candidates.student',
            'candidates.schoolClass',
            'candidates.scores.subject',
        ]);

        $results = $this->resultRows($mockExam);

        return Pdf::loadView('mock-exams.jury-decision-pdf', [
            'admitted' => $results->where('decision', 'Admis')->count(),
            'deferred' => $results->where('decision', 'A deliberer')->count(),
            'exam' => $mockExam,
            'rejected' => $results->where('decision', 'Ajourne')->count(),
            'results' => $results,
            'school' => SchoolSetting::query()->first(),
            'title' => 'Decision du jury',
        ])
            ->setPaper('a4')
            ->stream('decision-jury-' . Str::slug($mockExam->name) . '.pdf');
    }

    public function teacherFeesPdf(MockExam $mockExam)
    {
        $mockExam->load(['academicYear', 'classes.level', 'subjects.subject', 'subjects.scores', 'candidates']);

        return Pdf::loadView('mock-exams.teacher-fees-pdf', [
            'exam' => $mockExam,
            'school' => SchoolSetting::query()->first(),
            'title' => 'Honoraires professeurs',
        ])
            ->setPaper('a4')
            ->stream('honoraires-professeurs-' . Str::slug($mockExam->name) . '.pdf');
    }

    private function resultRows(MockExam $mockExam): Collection
    {
        $subjects = $mockExam->subjects->sortBy('position')->values();
        $coefficientTotal = $subjects->sum(fn ($subject) => (float) $subject->coefficient);

        return $mockExam->candidates
            ->map(function (MockExamCandidate $candidate) use ($subjects, $coefficientTotal) {
                $scores = $candidate->scores->keyBy('mock_exam_subject_id');
                $weightedTotal = 0.0;
                $usedCoefficients = 0.0;
                $missing = 0;

                foreach ($subjects as $subject) {
                    $score = $scores->get($subject->id);

                    if (! $score || $score->is_absent || $score->score === null) {
                        $missing++;
                        continue;
                    }

                    $normalizedScore = ((float) $score->score / (float) $subject->max_score) * 20;
                    $coefficient = (float) $subject->coefficient;
                    $weightedTotal += $normalizedScore * $coefficient;
                    $usedCoefficients += $coefficient;
                }

                $average = $usedCoefficients <= 0 ? null : round($weightedTotal / $usedCoefficients, 2);

                $decision = match (true) {
                    $average === null => 'Non classe',
                    $average >= 10 => 'Admis',
                    $average >= 8 => 'A deliberer',
                    default => 'Ajourne',
                };

                return [
                    'average' => $average,
                    'candidate' => $candidate,
                    'coefficient_total' => $coefficientTotal,
                    'decision' => $decision,
                    'missing' => $missing,
                    'scores' => $scores,
                    'used_coefficients' => $usedCoefficients,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['average'] ?? -1)
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $row['average'] === null ? null : $index + 1;

                return $row;
            });
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        abort_if(! $academicYear, 422, 'Aucune annee scolaire active.');

        return $academicYear;
    }
}
