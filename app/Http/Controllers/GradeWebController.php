<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Term;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GradeWebController extends Controller
{
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
                ->latest('assessment_date')
                ->latest('id')
                ->get();

            $selectedAssessment = $assessments->firstWhere('id', $request->integer('assessment_id')) ?? $assessments->first();

            $students = $this->studentsForClass($academicYear->id, $selectedClass->id);

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
            'classSubjects' => $classSubjects,
            'assessmentTypes' => AssessmentType::query()->where('status', 'active')->orderBy('name')->get(),
            'assessments' => $assessments,
            'selectedAssessment' => $selectedAssessment,
            'students' => $students,
            'gradesByStudent' => $gradesByStudent,
        ]);
    }

    public function storeAssessment(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'term_id' => [
                'required',
                Rule::exists('terms', 'id')->where('academic_year_id', $academicYear->id),
            ],
            'subject_id' => ['required', 'exists:subjects,id'],
            'assessment_type_id' => ['required', 'exists:assessment_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'assessment_date' => ['nullable', 'date'],
        ]);

        abort_unless($this->subjectBelongsToClass((int) $data['school_class_id'], (int) $data['subject_id']), 422, 'Matiere non affectee a cette classe.');

        $assessment = Assessment::create([
            ...$data,
            'academic_year_id' => $academicYear->id,
            'teacher_id' => auth()->id(),
        ]);

        return redirect()
            ->route('grades.index', [
                'school_class_id' => $assessment->school_class_id,
                'term_id' => $assessment->term_id,
                'assessment_id' => $assessment->id,
            ])
            ->with('success', 'Evaluation creee. Tu peux saisir les notes.');
    }

    public function updateGrades(Request $request, Assessment $assessment): RedirectResponse
    {
        abort_if($assessment->is_locked, 403, 'Cette evaluation est verrouillee.');

        $data = $request->validate([
            'grades' => ['nullable', 'array'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:' . (float) $assessment->max_score],
            'grades.*.is_absent' => ['nullable', 'boolean'],
            'grades.*.comment' => ['nullable', 'string', 'max:255'],
        ]);

        $studentIds = Enrollment::query()
            ->where('academic_year_id', $assessment->academic_year_id)
            ->where('school_class_id', $assessment->school_class_id)
            ->where('status', 'active')
            ->pluck('student_id')
            ->all();

        foreach ($studentIds as $studentId) {
            $line = $data['grades'][$studentId] ?? [];
            $isAbsent = (bool) ($line['is_absent'] ?? false);
            $score = $isAbsent ? null : ($line['score'] ?? null);

            Grade::updateOrCreate(
                [
                    'assessment_id' => $assessment->id,
                    'student_id' => $studentId,
                ],
                [
                    'score' => $score,
                    'is_absent' => $isAbsent,
                    'comment' => $line['comment'] ?? null,
                    'entered_by' => auth()->id(),
                ],
            );
        }

        return redirect()
            ->route('grades.index', [
                'school_class_id' => $assessment->school_class_id,
                'term_id' => $assessment->term_id,
                'assessment_id' => $assessment->id,
            ])
            ->with('success', 'Notes enregistrees.');
    }

    public function assessmentPdf(Assessment $assessment)
    {
        $assessment->load(['academicYear', 'term', 'schoolClass.level', 'subject', 'assessmentType', 'grades.student']);

        $students = $this->studentsForClass($assessment->academic_year_id, $assessment->school_class_id);
        $gradesByStudent = $assessment->grades->keyBy('student_id');
        $validGrades = $assessment->grades
            ->filter(fn (Grade $grade) => ! $grade->is_absent && $grade->score !== null);
        $absentCount = $assessment->grades->where('is_absent', true)->count();
        $enteredCount = $validGrades->count();

        $average = $validGrades->isEmpty()
            ? null
            : round($validGrades->avg(fn (Grade $grade) => ((float) $grade->score / (float) $assessment->max_score) * 20), 2);

        $filename = 'notes-' . Str::slug($assessment->schoolClass->name . '-' . $assessment->subject->name . '-' . $assessment->title) . '.pdf';

        return Pdf::loadView('grades.assessment-pdf', [
            'assessment' => $assessment,
            'absentCount' => $absentCount,
            'average' => $average,
            'enteredCount' => $enteredCount,
            'gradesByStudent' => $gradesByStudent,
            'school' => SchoolSetting::query()->first(),
            'students' => $students,
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function destroyAssessment(Assessment $assessment): RedirectResponse
    {
        abort_if($assessment->is_locked, 403, 'Cette evaluation est verrouillee.');

        $schoolClassId = $assessment->school_class_id;
        $termId = $assessment->term_id;
        $assessment->delete();

        return redirect()
            ->route('grades.index', [
                'school_class_id' => $schoolClassId,
                'term_id' => $termId,
            ])
            ->with('success', 'Evaluation supprimee.');
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        abort_if(! $academicYear, 422, 'Aucune annee scolaire active.');

        return $academicYear;
    }

    private function subjectBelongsToClass(int $schoolClassId, int $subjectId): bool
    {
        return ClassSubject::query()
            ->where('school_class_id', $schoolClassId)
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->exists();
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
