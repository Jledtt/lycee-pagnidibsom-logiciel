<?php

namespace App\Http\Controllers;

use App\Http\Requests\Timetable\UpdateTimetablePeriodsRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Timetable;
use App\Services\TimetableGridService;
use App\Services\TimetablePeriodService;
use App\Services\TimetableTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TimetableWebController extends Controller
{
    public function __construct(
        private readonly TimetableTemplateService $templates,
        private readonly TimetablePeriodService $periods,
        private readonly TimetableGridService $grids,
    ) {}

    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $selectedClass = $this->selectedClass($request, $classes);

        $timetable = $selectedClass
            ? Timetable::query()
                ->with(['schoolClass.level', 'academicYear', 'entries'])
                ->where('academic_year_id', $academicYear?->id)
                ->where('school_class_id', $selectedClass->id)
                ->first()
            : null;

        $timetables = Timetable::query()
            ->with(['schoolClass.level', 'academicYear'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->orderByDesc('updated_at')
            ->get();

        return view('timetables.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'timetable' => $timetable,
            'timetables' => $timetables,
            'days' => $this->templates->days(),
            'grid' => $timetable ? $this->grid($timetable) : [],
            'canApplyExample' => $this->templates->classHasExample($selectedClass),
        ]);
    }

    public function periods(): View
    {
        $academicYear = $this->requireActiveAcademicYear();

        return view('timetables.periods', [
            'academicYear' => $academicYear,
            'periods' => $this->templates->periods($academicYear, false),
        ]);
    }

    public function updatePeriods(UpdateTimetablePeriodsRequest $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $this->periods->synchronize($academicYear, $request->validated('periods'));

        return redirect()
            ->route('timetables.periods')
            ->with('success', 'Créneaux de l’année scolaire mis à jour.');
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')
                    ->where('academic_year_id', $academicYear->id),
            ],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $timetable = Timetable::query()->updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $data['school_class_id'],
            ],
            [
                'title' => $data['title'] ?: 'Emploi du temps',
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ],
        );

        if ($timetable->entries()->doesntExist()) {
            $this->templates->seedBlankEntries($timetable);
        }

        return redirect()
            ->route('timetables.edit', $timetable)
            ->with('success', 'Emploi du temps créé. Tu peux maintenant remplir la grille.');
    }

    public function edit(Timetable $timetable): View|RedirectResponse
    {
        if ($timetable->status === 'active') {
            return redirect()
                ->route('timetables.review', $timetable)
                ->withErrors(['timetable' => 'Cet emploi du temps est publié. Repasse-le en brouillon avant toute correction.']);
        }

        $timetable->load(['schoolClass.level', 'academicYear', 'entries']);

        $classSubjects = ClassSubject::query()
            ->with(['subject', 'teacher'])
            ->where('school_class_id', $timetable->school_class_id)
            ->where('is_active', true)
            ->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
            ->orderBy('subjects.name')
            ->select('class_subjects.*')
            ->get();

        return view('timetables.edit', [
            'timetable' => $timetable,
            'days' => $this->templates->days(),
            'grid' => $this->grid($timetable),
            'subjectOptions' => $this->templates->subjectOptions(),
            'classSubjects' => $classSubjects,
        ]);
    }

    public function update(Request $request, Timetable $timetable): RedirectResponse
    {
        if ($timetable->status === 'active') {
            return redirect()
                ->route('timetables.review', $timetable)
                ->withErrors(['timetable' => 'Cet emploi du temps est publié. Repasse-le en brouillon avant toute correction.']);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'principal_teacher' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', 'in:draft,archived'],
            'entries' => ['required', 'array'],
            'entries.*.entry_id' => [
                'nullable',
                Rule::exists('timetable_entries', 'id')->where('timetable_id', $timetable->id),
            ],
            'entries.*.sort_order' => ['required', 'integer', 'min:1', 'max:99'],
            'entries.*.period_label' => ['required', 'string', 'max:40'],
            'entries.*.starts_at' => ['nullable', 'date_format:H:i'],
            'entries.*.ends_at' => ['nullable', 'date_format:H:i'],
            'entries.*.day_of_week' => ['required', 'string', 'max:20'],
            'entries.*.timetable_period_id' => [
                'nullable',
                Rule::exists('timetable_periods', 'id')->where('academic_year_id', $timetable->academic_year_id),
            ],
            'entries.*.class_subject_id' => [
                'nullable',
                Rule::exists('class_subjects', 'id')
                    ->where('school_class_id', $timetable->school_class_id)
                    ->where('is_active', true),
            ],
            'entries.*.subject_name' => ['nullable', 'string', 'max:120'],
            'entries.*.teacher_name' => ['nullable', 'string', 'max:160'],
            'entries.*.room' => ['nullable', 'string', 'max:60'],
            'entries.*.is_break' => ['nullable', 'boolean'],
        ]);

        $attributes = [
            'title' => $data['title'],
            'principal_teacher' => $data['principal_teacher'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'],
        ];

        $this->grids->update($timetable, $attributes, $data['entries']);

        return redirect()
            ->route('timetables.review', $timetable)
            ->with('success', 'Emploi du temps mis à jour.');
    }

    public function applyExample(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')
                    ->where('academic_year_id', $academicYear->id),
            ],
        ]);

        $timetable = Timetable::query()->updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $data['school_class_id'],
            ],
            [
                'title' => 'Emploi du temps provisoire',
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ],
        );

        $timetable->load('schoolClass');

        if (! $this->templates->applyExample($timetable)) {
            return redirect()
                ->route('timetables.index', ['school_class_id' => $data['school_class_id']])
                ->withErrors(['school_class_id' => 'Aucun modèle 2025-2026 n’est disponible pour cette classe.']);
        }

        return redirect()
            ->route('timetables.edit', $timetable)
            ->with('success', 'Modèle 2025-2026 appliqué. Vérifie puis adapte la grille.');
    }

    public function pdf(Timetable $timetable)
    {
        $timetable->load(['schoolClass.level', 'academicYear', 'entries']);

        $filename = 'emploi-du-temps-'.str($timetable->schoolClass->name)->slug().'.pdf';

        return Pdf::loadView('timetables.pdf', [
            'timetable' => $timetable,
            'days' => $this->templates->days(),
            'grid' => $this->grid($timetable),
            'school' => SchoolSetting::query()->first(),
        ])
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
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
        if ($classes->isEmpty()) {
            return null;
        }

        return $classes->firstWhere('id', $request->integer('school_class_id')) ?? $classes->first();
    }

    private function grid(Timetable $timetable): array
    {
        $rows = [];

        foreach ($this->templates->periods($timetable->academicYear) as $period) {
            $rows[$period['label']] = [
                'id' => $period['id'] ?? null,
                'sort_order' => $period['sort_order'],
                'period_label' => $period['label'],
                'starts_at' => $period['starts_at'],
                'ends_at' => $period['ends_at'],
                'is_break' => $period['is_break'],
                'days' => [],
            ];
        }

        foreach ($timetable->entries->sortBy([['sort_order', 'asc'], ['day_of_week', 'asc']]) as $entry) {
            $key = $entry->period_label;

            $rows[$key] ??= [
                'id' => $entry->timetable_period_id,
                'sort_order' => $entry->sort_order,
                'period_label' => $entry->period_label,
                'starts_at' => $entry->starts_at ? substr((string) $entry->starts_at, 0, 5) : null,
                'ends_at' => $entry->ends_at ? substr((string) $entry->ends_at, 0, 5) : null,
                'is_break' => $entry->is_break,
                'days' => [],
            ];

            $rows[$key]['days'][$entry->day_of_week] = $entry;
        }

        return collect($rows)
            ->sortBy('sort_order')
            ->values()
            ->all();
    }
}
