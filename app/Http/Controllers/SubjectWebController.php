<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\PagnidibsomClassSubjectSetupService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();

        $classes = SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $selectedClass = $this->selectedClass($request, $classes);
        $classSubjects = collect();

        if ($selectedClass) {
            $classSubjects = ClassSubject::query()
                ->with('subject')
                ->where('school_class_id', $selectedClass->id)
                ->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
                ->orderBy('subjects.name')
                ->select('class_subjects.*')
                ->get();
        }

        $subjects = Subject::query()
            ->orderByRaw("status = 'inactive'")
            ->orderBy('name')
            ->get();

        return view('subjects.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'subjects' => $subjects,
            'classSubjects' => $classSubjects,
            'availableSubjects' => $subjects
                ->where('status', 'active')
                ->reject(fn (Subject $subject) => $classSubjects->contains('subject_id', $subject->id)),
            'suggestedSubjects' => $selectedClass ? $this->suggestedSubjectsForClass($selectedClass) : [],
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('subjects', 'name')],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('subjects', 'code')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
        ]);

        Subject::create([
            'name' => Str::of($data['name'])->squish()->toString(),
            'code' => filled($data['code'] ?? null) ? Str::upper(Str::of($data['code'])->squish()->toString()) : null,
            'status' => $data['status'],
        ]);

        return $this->backToIndex($data['school_class_id'] ?? null)
            ->with('success', 'Matière ajoutée.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('subjects', 'name')->ignore($subject->id)],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('subjects', 'code')->ignore($subject->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
        ]);

        $subject->update([
            'name' => Str::of($data['name'])->squish()->toString(),
            'code' => filled($data['code'] ?? null) ? Str::upper(Str::of($data['code'])->squish()->toString()) : null,
            'status' => $data['status'],
        ]);

        return $this->backToIndex($data['school_class_id'] ?? null)
            ->with('success', 'Matière mise à jour.');
    }

    public function storeClassSubject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'coefficient' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            ClassSubject::create([
                'school_class_id' => $data['school_class_id'],
                'subject_id' => $data['subject_id'],
                'coefficient' => $data['coefficient'],
                'weekly_hours' => $data['weekly_hours'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        } catch (QueryException) {
            return $this->backToIndex($data['school_class_id'])
                ->withErrors(['subject_id' => 'Cette matière est déjà affectée à cette classe.']);
        }

        return $this->backToIndex($data['school_class_id'])
            ->with('success', 'Matière affectée à la classe.');
    }

    public function updateClassSubject(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        $data = $request->validate([
            'coefficient' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'is_active' => ['required', 'boolean'],
        ]);

        $classSubject->update($data);

        return $this->backToIndex($classSubject->school_class_id)
            ->with('success', 'Coefficient et volume horaire mis à jour.');
    }

    public function destroyClassSubject(ClassSubject $classSubject): RedirectResponse
    {
        $schoolClassId = $classSubject->school_class_id;
        $classSubject->delete();

        return $this->backToIndex($schoolClassId)
            ->with('success', 'Matière retirée de la classe.');
    }

    public function applyDefaults(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
        ]);

        $schoolClass = SchoolClass::query()
            ->with('level')
            ->findOrFail($data['school_class_id']);

        $created = 0;

        foreach ($this->suggestedSubjectsForClass($schoolClass) as $item) {
            $subject = Subject::query()
                ->where('code', $item['code'])
                ->orWhere('name', $item['name'])
                ->first();

            if (! $subject) {
                $subject = Subject::create([
                    'name' => $item['name'],
                    'code' => $item['code'],
                    'status' => 'active',
                ]);
            }

            if ($subject->name !== $item['name'] || $subject->code !== $item['code'] || $subject->status !== 'active') {
                $subject->forceFill([
                    'name' => $item['name'],
                    'code' => $item['code'],
                    'status' => 'active',
                ])->save();
            }

            ClassSubject::updateOrCreate(
                [
                    'school_class_id' => $schoolClass->id,
                    'subject_id' => $subject->id,
                ],
                [
                    'coefficient' => $item['coefficient'],
                    'is_active' => true,
                ],
            );

            $created++;
        }

        return $this->backToIndex($schoolClass->id)
            ->with('success', $created.' matière(s) proposées appliquées à '.$schoolClass->name.'.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function selectedClass(Request $request, $classes): ?SchoolClass
    {
        if ($classes->isEmpty()) {
            return null;
        }

        $requestedId = $request->integer('school_class_id');

        return $classes->firstWhere('id', $requestedId) ?? $classes->first();
    }

    private function suggestedSubjectsForClass(SchoolClass $schoolClass): array
    {
        $officialSubjects = app(PagnidibsomClassSubjectSetupService::class)
            ->suggestedSubjectsForClass($schoolClass);

        if ($officialSubjects !== []) {
            return $officialSubjects;
        }

        $name = Str::lower($schoolClass->name.' '.($schoolClass->level?->name ?? ''));

        if (str_contains($name, 'bep') || str_contains($name, 'genie') || str_contains($name, 'electro')) {
            return [
                ['name' => 'Français', 'code' => 'FR', 'coefficient' => 4],
                ['name' => 'Mathématiques appliquées', 'code' => 'MATH_APP', 'coefficient' => 4],
                ['name' => 'Anglais', 'code' => 'ANG', 'coefficient' => 2],
                ['name' => 'Sciences physiques', 'code' => 'SP', 'coefficient' => 3],
                ['name' => 'Technologie', 'code' => 'TECH', 'coefficient' => 4],
                ['name' => 'Dessin technique', 'code' => 'DESS_TECH', 'coefficient' => 3],
                ['name' => 'EPS', 'code' => 'EPS', 'coefficient' => 2],
            ];
        }

        if (str_contains($name, '2nde') || str_contains($name, '1re') || str_contains($name, 'terminale')) {
            return [
                ['name' => 'Français', 'code' => 'FR', 'coefficient' => 4],
                ['name' => 'Mathématiques', 'code' => 'MATH', 'coefficient' => 4],
                ['name' => 'Anglais', 'code' => 'ANG', 'coefficient' => 3],
                ['name' => 'Histoire-Géographie', 'code' => 'HG', 'coefficient' => 3],
                ['name' => 'SVT', 'code' => 'SVT', 'coefficient' => 3],
                ['name' => 'Physique-Chimie', 'code' => 'PC', 'coefficient' => 3],
                ['name' => 'Philosophie', 'code' => 'PHILO', 'coefficient' => 2],
                ['name' => 'EPS', 'code' => 'EPS', 'coefficient' => 2],
                ['name' => 'Éducation civique et morale', 'code' => 'ECM', 'coefficient' => 1],
            ];
        }

        if (str_contains($name, '4') || str_contains($name, '3')) {
            return [
                ['name' => 'Français', 'code' => 'FR', 'coefficient' => 5],
                ['name' => 'Mathématiques', 'code' => 'MATH', 'coefficient' => 5],
                ['name' => 'Anglais', 'code' => 'ANG', 'coefficient' => 3],
                ['name' => 'Histoire-Géographie', 'code' => 'HG', 'coefficient' => 3],
                ['name' => 'SVT', 'code' => 'SVT', 'coefficient' => 2],
                ['name' => 'Physique-Chimie', 'code' => 'PC', 'coefficient' => 2],
                ['name' => 'EPS', 'code' => 'EPS', 'coefficient' => 2],
                ['name' => 'Éducation civique et morale', 'code' => 'ECM', 'coefficient' => 1],
                ['name' => 'Allemand', 'code' => 'ALL', 'coefficient' => 1],
            ];
        }

        return [
            ['name' => 'Français', 'code' => 'FR', 'coefficient' => 5],
            ['name' => 'Mathématiques', 'code' => 'MATH', 'coefficient' => 5],
            ['name' => 'Anglais', 'code' => 'ANG', 'coefficient' => 3],
            ['name' => 'Histoire-Géographie', 'code' => 'HG', 'coefficient' => 3],
            ['name' => 'SVT', 'code' => 'SVT', 'coefficient' => 2],
            ['name' => 'EPS', 'code' => 'EPS', 'coefficient' => 2],
            ['name' => 'Éducation civique et morale', 'code' => 'ECM', 'coefficient' => 1],
            ['name' => 'Technologie', 'code' => 'TECH', 'coefficient' => 1],
            ['name' => 'Art et culture', 'code' => 'ART', 'coefficient' => 1],
        ];
    }

    private function backToIndex(?int $schoolClassId): RedirectResponse
    {
        return redirect()->route('subjects.index', array_filter([
            'school_class_id' => $schoolClassId,
        ]));
    }
}
