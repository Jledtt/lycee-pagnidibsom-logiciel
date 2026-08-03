<?php

namespace App\Http\Controllers;

use App\Http\Requests\Grade\StoreAssessmentRequest;
use App\Http\Requests\Grade\UpdateGradesRequest;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Term;
use App\Services\GradeEntryService;
use App\Services\TermPeriodService;
use App\Services\XlsxExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GradeWebController extends Controller
{
    public function __construct(
        private readonly GradeEntryService $gradeEntryService,
        private readonly TermPeriodService $termPeriodService,
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

        $classSubjects = collect();
        $assessments = collect();
        $students = collect();
        $selectedAssessment = null;
        $gradesByStudent = collect();

        if ($selectedClass && $selectedTerm) {
            $classSubjects = ClassSubject::query()
                ->with('subject')
                ->where('school_class_id', $selectedClass->id)
                ->where('is_active', true)
                ->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
                ->orderBy('subjects.name')
                ->select('class_subjects.*')
                ->get();

            $assessments = Assessment::query()
                ->with(['subject', 'assessmentType'])
                ->withCount('grades')
                ->where('academic_year_id', $academicYear->id)
                ->where('school_class_id', $selectedClass->id)
                ->where('term_id', $selectedTerm->id)
                ->when($selectedTermPeriod, fn ($query) => $query->where('term_period_id', $selectedTermPeriod->id))
                ->latest('assessment_date')
                ->latest('id')
                ->get();

            $selectedAssessment = $assessments->firstWhere('id', $request->integer('assessment_id')) ?? $assessments->first();

            $students = $this->gradeEntryService->studentsForClass($academicYear->id, $selectedClass->id);

            if ($selectedAssessment) {
                $gradesByStudent = Grade::query()
                    ->where('assessment_id', $selectedAssessment->id)
                    ->get()
                    ->keyBy('student_id');
            }
        }

        return view('grades.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'terms' => $terms,
            'selectedClass' => $selectedClass,
            'selectedTerm' => $selectedTerm,
            'selectedTermPeriod' => $selectedTermPeriod,
            'classSubjects' => $classSubjects,
            'assessmentTypes' => AssessmentType::query()->where('status', 'active')->orderBy('name')->get(),
            'assessments' => $assessments,
            'selectedAssessment' => $selectedAssessment,
            'students' => $students,
            'termPeriods' => $termPeriods,
            'gradesByStudent' => $gradesByStudent,
            'entryModeLabels' => Assessment::entryModeLabels(),
            'gradeStatusLabels' => Grade::statusLabels(),
        ]);
    }

    public function storeAssessment(StoreAssessmentRequest $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $request->validated();

        abort_unless($this->gradeEntryService->subjectBelongsToClass((int) $data['school_class_id'], (int) $data['subject_id']), 422, 'Matière non affectée à cette classe.');
        abort_if(
            $this->gradeEntryService->classTermIsLocked((int) $data['school_class_id'], (int) $data['term_id']) && ! $request->user()?->can('grades.unlock'),
            403,
            'Le conseil de classe est verrouille pour ce trimestre.'
        );

        $assessment = $this->gradeEntryService->createAssessment($academicYear, $data, $request->user());

        return redirect()
            ->route('grades.index', [
                'school_class_id' => $assessment->school_class_id,
                'term_id' => $assessment->term_id,
                'term_period_id' => $assessment->term_period_id,
                'assessment_id' => $assessment->id,
            ])
            ->with('success', 'Évaluation créée. Tu peux saisir les notes.');
    }

    public function updateGrades(UpdateGradesRequest $request, Assessment $assessment): RedirectResponse
    {
        abort_if($assessment->is_locked && ! $request->user()?->can('grades.unlock'), 403, 'Cette evaluation est verrouillee.');

        $this->gradeEntryService->updateGrades($assessment, $request->validated('grades') ?? [], $request->user());

        return redirect()
            ->route('grades.index', [
                'school_class_id' => $assessment->school_class_id,
                'term_id' => $assessment->term_id,
                'term_period_id' => $assessment->term_period_id,
                'assessment_id' => $assessment->id,
            ])
            ->with('success', 'Notes enregistrées.');
    }

    public function assessmentPdf(Assessment $assessment)
    {
        $assessment->load(['academicYear', 'term', 'termPeriod', 'schoolClass.level', 'subject', 'assessmentType', 'teacher', 'grades.student']);

        $students = $this->gradeEntryService->studentsForClass($assessment->academic_year_id, $assessment->school_class_id);
        $gradesByStudent = $assessment->grades->keyBy('student_id');
        $validGrades = $assessment->grades
            ->filter(fn (Grade $grade) => $grade->isCounted() && $grade->score !== null);
        $absentCount = $assessment->grades
            ->filter(fn (Grade $grade) => $grade->resolvedStatus() === Grade::STATUS_ABSENT)
            ->count();
        $excludedCount = $assessment->grades
            ->filter(fn (Grade $grade) => ! $grade->isCounted() && $grade->resolvedStatus() !== Grade::STATUS_ABSENT)
            ->count();
        $enteredCount = $validGrades->count();

        $average = $validGrades->isEmpty()
            ? null
            : round($validGrades->avg(fn (Grade $grade) => ((float) $grade->score / (float) $assessment->max_score) * 20), 2);

        $filename = 'notes-'.Str::slug($assessment->schoolClass->name.'-'.$assessment->subject->name.'-'.$assessment->title).'.pdf';
        $coefficient = ClassSubject::query()
            ->where('school_class_id', $assessment->school_class_id)
            ->where('subject_id', $assessment->subject_id)
            ->value('coefficient');

        return Pdf::loadView('grades.assessment-pdf', [
            'assessment' => $assessment,
            'absentCount' => $absentCount,
            'excludedCount' => $excludedCount,
            'average' => $average,
            'coefficient' => $coefficient,
            'enteredCount' => $enteredCount,
            'gradesByStudent' => $gradesByStudent,
            'school' => SchoolSetting::query()->first(),
            'statusLabels' => Grade::statusLabels(),
            'students' => $students,
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function assessmentExport(Assessment $assessment, XlsxExportService $xlsxExport)
    {
        $assessment->load(['academicYear', 'term', 'termPeriod', 'schoolClass.level', 'subject', 'assessmentType', 'grades.student']);

        $students = $this->gradeEntryService->studentsForClass($assessment->academic_year_id, $assessment->school_class_id);
        $gradesByStudent = $assessment->grades->keyBy('student_id');
        $filename = 'notes-'.Str::slug($assessment->schoolClass->name.'-'.$assessment->subject->name.'-'.$assessment->title).'.xlsx';

        return $xlsxExport->download($filename, [
            'Matricule',
            'Élève',
            'Classe',
            'Période',
            'Matière',
            'Evaluation',
            'Note',
            'Note sur',
            'Note / 20',
            'Statut',
            'Commentaire',
        ], $students->map(function (Student $student) use ($assessment, $gradesByStudent) {
            $grade = $gradesByStudent->get($student->id);
            $status = $grade?->resolvedStatus() ?? Grade::STATUS_GRADED;
            $score = ($grade && $grade->isCounted()) ? $grade->score : null;
            $scoreOnTwenty = $score === null ? null : round(((float) $score / (float) $assessment->max_score) * 20, 2);

            return [
                $student->matricule,
                $student->full_name,
                $assessment->schoolClass->name,
                $assessment->termPeriod?->name ?? '-',
                $assessment->subject->name,
                $assessment->title,
                $score,
                $assessment->max_score,
                $scoreOnTwenty,
                Grade::statusLabels()[$status] ?? $status,
                $grade?->comment,
            ];
        }));
    }

    public function registerPdf(Assessment $assessment)
    {
        $assessment->load(['academicYear', 'term', 'termPeriod', 'schoolClass.level', 'subject']);

        $assessments = Assessment::query()
            ->with(['assessmentType', 'grades'])
            ->where('academic_year_id', $assessment->academic_year_id)
            ->where('school_class_id', $assessment->school_class_id)
            ->where('term_id', $assessment->term_id)
            ->where('subject_id', $assessment->subject_id)
            ->when(
                $assessment->term_period_id,
                fn ($query) => $query->where('term_period_id', $assessment->term_period_id),
                fn ($query) => $query->whereNull('term_period_id'),
            )
            ->orderBy('assessment_date')
            ->orderBy('id')
            ->get();
        $students = $this->gradeEntryService->studentsForClass($assessment->academic_year_id, $assessment->school_class_id);
        $coefficient = (float) (ClassSubject::query()
            ->where('school_class_id', $assessment->school_class_id)
            ->where('subject_id', $assessment->subject_id)
            ->value('coefficient') ?? 0);
        $rows = $students->map(function (Student $student) use ($assessments, $coefficient) {
            $grades = $assessments->mapWithKeys(fn (Assessment $item) => [
                $item->id => $item->grades->firstWhere('student_id', $student->id),
            ]);
            $normalizedScores = $assessments
                ->map(function (Assessment $item) use ($grades) {
                    $grade = $grades->get($item->id);

                    if (! $grade?->isCounted() || $grade->score === null || (float) $item->max_score <= 0) {
                        return null;
                    }

                    return ((float) $grade->score / (float) $item->max_score) * 20;
                })
                ->filter(fn ($score) => $score !== null);
            $average = $normalizedScores->isEmpty() ? null : round($normalizedScores->avg(), 2);

            return [
                'student' => $student,
                'grades' => $grades,
                'average' => $average,
                'weighted' => $average === null ? null : round($average * $coefficient, 2),
            ];
        });
        $filename = 'registre-notes-'.Str::slug(
            $assessment->schoolClass->name.'-'.$assessment->subject->name.'-'.($assessment->termPeriod?->name ?? $assessment->term->name)
        ).'.pdf';

        return Pdf::loadView('grades.register-pdf', [
            'assessment' => $assessment,
            'assessments' => $assessments,
            'coefficient' => $coefficient,
            'rows' => $rows,
            'school' => SchoolSetting::query()->first(),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function destroyAssessment(Assessment $assessment): RedirectResponse
    {
        abort_if($assessment->is_locked && ! request()->user()?->can('grades.unlock'), 403, 'Cette evaluation est verrouillee.');

        $schoolClassId = $assessment->school_class_id;
        $termId = $assessment->term_id;
        $termPeriodId = $assessment->term_period_id;
        $assessment->delete();

        return redirect()
            ->route('grades.index', [
                'school_class_id' => $schoolClassId,
                'term_id' => $termId,
                'term_period_id' => $termPeriodId,
            ])
            ->with('success', 'Évaluation supprimée.');
    }

    public function lockAssessment(Assessment $assessment): RedirectResponse
    {
        $assessment->update(['is_locked' => true]);

        return $this->backToAssessment($assessment)
            ->with('success', 'Évaluation verrouillée.');
    }

    public function unlockAssessment(Assessment $assessment): RedirectResponse
    {
        $assessment->update(['is_locked' => false]);

        return $this->backToAssessment($assessment)
            ->with('success', 'Évaluation déverrouillée.');
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
    }

    private function backToAssessment(Assessment $assessment): RedirectResponse
    {
        return redirect()->route('grades.index', [
            'school_class_id' => $assessment->school_class_id,
            'term_id' => $assessment->term_id,
            'term_period_id' => $assessment->term_period_id,
            'assessment_id' => $assessment->id,
        ]);
    }
}
