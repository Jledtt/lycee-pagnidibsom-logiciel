<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\MockExam;
use App\Models\MockExamCandidate;
use App\Models\MockExamScore;
use App\Models\MockExamSubject;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Services\MockExamService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            ->with(['classes.level', 'term'])
            ->withCount(['candidates', 'subjects'])
            ->where('academic_year_id', $academicYear->id)
            ->latest('id')
            ->get();

        $selectedExam = $exams->firstWhere('id', $request->integer('mock_exam_id')) ?? $exams->first();

        if ($selectedExam) {
            $selectedExam->load([
                'classes.level',
                'term',
                'subjects.subject',
                'candidates.student',
                'candidates.schoolClass',
            ]);
        }

        $terms = Term::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('position')
            ->get();

        $suggestedClassIds = $classes
            ->filter(fn ($class) => Str::contains(Str::lower($class->name.' '.$class->level?->name), ['3e', '3eme', 'troisieme', 'terminale', 'tle']))
            ->pluck('id')
            ->all();

        return view('mock-exams.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'exams' => $exams,
            'juryDecisionLabels' => $this->juryDecisionLabels(),
            'selectedExam' => $selectedExam,
            'suggestedClassIds' => $suggestedClassIds,
            'terms' => $terms,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'exam_type' => ['required', 'in:trimestriel,bepc_blanc,bac_blanc'],
            'term_id' => [
                'nullable',
                'integer',
                Rule::exists('terms', 'id')
                    ->where('academic_year_id', $academicYear->id),
            ],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'school_class_ids' => ['required', 'array', 'min:1'],
            'school_class_ids.*' => [
                'integer',
                Rule::exists('school_classes', 'id')
                    ->where('academic_year_id', $academicYear->id),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $exam = $this->mockExamService->createExam($academicYear, $data);

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $exam->id])
            ->with('success', 'Session d’examen blanc créée avec candidats et matières de base.');
    }

    public function updateResultStatus(Request $request, MockExam $mockExam): RedirectResponse
    {
        $data = $request->validate([
            'result_status' => ['required', 'in:preparation,provisoire,corrige,definitif,verrouille'],
        ]);

        if ($mockExam->is_locked && ! $request->user()->hasRole('admin')) {
            abort(403, 'Seul un administrateur peut corriger une session verrouillee.');
        }

        $updates = ['result_status' => $data['result_status']];

        if (in_array($data['result_status'], ['provisoire', 'corrige'], true)) {
            $updates['validated_at'] = now();
            $updates['validated_by'] = $request->user()->id;
        }

        if ($data['result_status'] === 'definitif') {
            $updates['finalized_at'] = now();
            $updates['finalized_by'] = $request->user()->id;
        }

        if ($data['result_status'] === 'verrouille') {
            $updates['locked_at'] = now();
            $updates['locked_by'] = $request->user()->id;
            $updates['finalized_at'] = $mockExam->finalized_at ?? now();
            $updates['finalized_by'] = $mockExam->finalized_by ?? $request->user()->id;
        }

        if ($mockExam->is_locked && $data['result_status'] !== 'verrouille') {
            $updates['locked_at'] = null;
            $updates['locked_by'] = null;
        }

        $mockExam->update($updates);

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExam->id])
            ->with('success', 'Statut des résultats mis à jour.');
    }

    public function syncCandidates(MockExam $mockExam): RedirectResponse
    {
        $count = $this->mockExamService->syncCandidates($mockExam);

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExam->id])
            ->with('success', $count . ' candidat(s) synchronisé(s).');
    }

    public function generateAnonymousCodes(Request $request, MockExam $mockExam): RedirectResponse
    {
        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:8'],
        ]);

        $count = $this->mockExamService->generateAnonymousCodes($mockExam, $data['prefix'] ?: 'X');

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExam->id])
            ->with('success', $count . ' anonymat(s) généré(s).');
    }

    public function distributeRooms(Request $request, MockExam $mockExam): RedirectResponse
    {
        $data = $request->validate([
            'room_count' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $count = $this->mockExamService->distributeRooms($mockExam, (int) $data['room_count']);

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExam->id])
            ->with('success', $count . ' candidat(s) réparti(s) en salle.');
    }

    public function updateSubjectTracking(Request $request, MockExamSubject $mockExamSubject): RedirectResponse
    {
        $mockExamSubject->load('mockExam');
        $this->ensureExamEditable($request, $mockExamSubject->mockExam);

        $data = $request->validate([
            'exam_date' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'supervisor_one' => ['nullable', 'string', 'max:120'],
            'supervisor_two' => ['nullable', 'string', 'max:120'],
            'expected_copies' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'received_copies' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'absent_count' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'incident_notes' => ['nullable', 'string', 'max:1200'],
            'copies_received_at' => ['nullable', 'date'],
            'copy_receiver_name' => ['nullable', 'string', 'max:120'],
            'correction_teacher_name' => ['nullable', 'string', 'max:120'],
            'fee_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'fee_status' => ['required', 'in:pending,approved,paid'],
            'fee_paid_at' => ['nullable', 'date'],
            'fee_payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $mockExamSubject->update($data);

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExamSubject->mock_exam_id])
            ->with('success', 'Suivi de la matière mis à jour.');
    }

    public function subjectScores(Request $request, MockExam $mockExam, MockExamSubject $mockExamSubject): View
    {
        abort_unless($mockExamSubject->mock_exam_id === $mockExam->id, 404);

        $mockExam->load(['academicYear', 'classes.level']);
        $mockExamSubject->load(['subject', 'scores']);

        $candidates = $mockExam->candidates()
            ->with(['student', 'schoolClass', 'scores'])
            ->join('students', 'students.id', '=', 'mock_exam_candidates.student_id')
            ->orderByRaw('case when mock_exam_candidates.anonymous_code is null then 1 else 0 end')
            ->orderBy('mock_exam_candidates.anonymous_code')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('mock_exam_candidates.*')
            ->get();

        return view('mock-exams.subject-scores', [
            'canEditExam' => $request->user()->can('mock_exams.manage') && (! $mockExam->is_locked || $request->user()->hasRole('admin')),
            'candidates' => $candidates,
            'exam' => $mockExam,
            'scores' => MockExamScore::query()
                ->where('mock_exam_subject_id', $mockExamSubject->id)
                ->get()
                ->keyBy('mock_exam_candidate_id'),
            'subject' => $mockExamSubject,
        ]);
    }

    public function updateSubjectScores(Request $request, MockExam $mockExam, MockExamSubject $mockExamSubject): RedirectResponse
    {
        abort_unless($mockExamSubject->mock_exam_id === $mockExam->id, 404);
        $this->ensureExamEditable($request, $mockExam);

        $data = $request->validate([
            'scores' => ['nullable', 'array'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:' . (float) $mockExamSubject->max_score],
            'scores.*.is_absent' => ['nullable', 'boolean'],
            'scores.*.observation' => ['nullable', 'string', 'max:255'],
        ]);

        $candidateIds = $mockExam->candidates()->pluck('id')->all();

        foreach (($data['scores'] ?? []) as $candidateId => $scoreData) {
            if (! in_array((int) $candidateId, $candidateIds, true)) {
                continue;
            }

            $isAbsent = (bool) ($scoreData['is_absent'] ?? false);

            MockExamScore::query()->updateOrCreate([
                'mock_exam_subject_id' => $mockExamSubject->id,
                'mock_exam_candidate_id' => $candidateId,
            ], [
                'score' => $isAbsent ? null : ($scoreData['score'] ?? null),
                'is_absent' => $isAbsent,
                'observation' => $scoreData['observation'] ?? null,
            ]);
        }

        return redirect()
            ->route('mock-exams.subjects.scores', [$mockExam, $mockExamSubject])
            ->with('success', 'Notes de l’épreuve enregistrées.');
    }

    public function updateJuryDecisions(Request $request, MockExam $mockExam): RedirectResponse
    {
        $this->ensureExamEditable($request, $mockExam);

        $data = $request->validate([
            'candidates' => ['nullable', 'array'],
            'candidates.*.jury_decision' => ['nullable', 'in:admitted,repeat,excluded,oriented,ec,none'],
            'candidates.*.jury_observation' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach (($data['candidates'] ?? []) as $candidateId => $candidateData) {
            MockExamCandidate::query()
                ->where('mock_exam_id', $mockExam->id)
                ->whereKey($candidateId)
                ->update([
                    'jury_decision' => $candidateData['jury_decision'] ?: null,
                    'jury_observation' => $candidateData['jury_observation'] ?? null,
                    'jury_decided_at' => now(),
                    'jury_decided_by' => $request->user()->id,
                ]);
        }

        return redirect()
            ->route('mock-exams.index', ['mock_exam_id' => $mockExam->id])
            ->with('success', 'Décisions du jury mises à jour.');
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

    public function scoreSheetPdf(MockExam $mockExam, MockExamSubject $mockExamSubject)
    {
        abort_unless($mockExamSubject->mock_exam_id === $mockExam->id, 404);

        $mockExam->load(['academicYear', 'classes.level']);
        $mockExamSubject->load(['subject']);

        $candidates = $mockExam->candidates()
            ->with(['student', 'schoolClass'])
            ->leftJoin('mock_exam_scores', function ($join) use ($mockExamSubject) {
                $join->on('mock_exam_scores.mock_exam_candidate_id', '=', 'mock_exam_candidates.id')
                    ->where('mock_exam_scores.mock_exam_subject_id', $mockExamSubject->id);
            })
            ->orderByRaw('case when mock_exam_candidates.anonymous_code is null then 1 else 0 end')
            ->orderBy('mock_exam_candidates.anonymous_code')
            ->orderBy('mock_exam_candidates.id')
            ->select('mock_exam_candidates.*', 'mock_exam_scores.score as sheet_score', 'mock_exam_scores.is_absent as sheet_is_absent', 'mock_exam_scores.observation as sheet_observation')
            ->get();

        return Pdf::loadView('mock-exams.score-sheet-pdf', [
            'candidates' => $candidates,
            'exam' => $mockExam,
            'school' => SchoolSetting::query()->first(),
            'subject' => $mockExamSubject,
            'title' => 'Relevé de notes',
        ])
            ->setPaper('a4')
            ->stream('saisie-notes-' . Str::slug($mockExam->name . '-' . $mockExamSubject->subject?->name) . '.pdf');
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

        $title = $status === 'definitif' ? 'Résultats définitifs' : 'Résultats provisoires';

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
            'juryDecisionLabels' => $this->juryDecisionLabels(),
            'rejected' => $results->where('decision', 'Ajourne')->count(),
            'results' => $results,
            'school' => SchoolSetting::query()->first(),
            'title' => 'Décision du jury',
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

    private function ensureExamEditable(Request $request, MockExam $mockExam): void
    {
        if ($mockExam->is_locked && ! $request->user()->hasRole('admin')) {
            abort(403, 'Session verrouillee. Seul un administrateur peut corriger.');
        }
    }

    private function juryDecisionLabels(): array
    {
        return [
            'admitted' => 'Admis / Passe',
            'repeat' => 'Redouble',
            'excluded' => 'Exclu',
            'oriented' => 'Oriente',
            'ec' => 'EC',
            'none' => 'A determiner',
        ];
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
    }
}
