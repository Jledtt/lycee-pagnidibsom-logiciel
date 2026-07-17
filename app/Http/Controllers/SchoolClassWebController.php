<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolClassWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();

        $classes = SchoolClass::query()
            ->with('level')
            ->withCount(['enrollments' => fn ($query) => $query->where('status', 'active')])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('classes.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('classes.create', [
            'academicYear' => $this->activeAcademicYear(),
            'schoolClass' => new SchoolClass(['status' => 'active']),
            'levels' => Level::query()->orderBy('position')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $this->validateClass($request, $academicYear);

        $schoolClass = SchoolClass::create($data + [
            'academic_year_id' => $academicYear->id,
        ]);

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('success', 'Classe creee avec succes.');
    }

    public function show(SchoolClass $schoolClass): View
    {
        $academicYear = $this->activeAcademicYear();

        $schoolClass->load([
            'level',
            'enrollments' => fn ($query) => $query
                ->with(['student.guardians'])
                ->where('status', 'active')
                ->orderBy('created_at'),
        ]);

        $availableStudents = Student::query()
            ->where('status', 'active')
            ->whereDoesntHave('enrollments', function ($query) use ($academicYear) {
                $query->when($academicYear, fn ($subQuery) => $subQuery->where('academic_year_id', $academicYear->id))
                    ->where('status', 'active');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('classes.show', [
            'academicYear' => $academicYear,
            'schoolClass' => $schoolClass,
            'availableStudents' => $availableStudents,
        ]);
    }

    public function edit(SchoolClass $schoolClass): View
    {
        return view('classes.edit', [
            'academicYear' => $this->activeAcademicYear(),
            'schoolClass' => $schoolClass,
            'levels' => Level::query()->orderBy('position')->get(),
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $schoolClass->update($this->validateClass($request, $academicYear, $schoolClass));

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('success', 'Classe mise a jour.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->update(['status' => 'archived']);

        return redirect()
            ->route('classes.index')
            ->with('success', 'Classe archivee.');
    }

    public function attachStudent(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'enrollment_date' => ['nullable', 'date'],
            'type' => ['required', 'in:new,renewal,transfer'],
            'notes' => ['nullable', 'string'],
        ]);

        Enrollment::updateOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'student_id' => $data['student_id'],
            ],
            [
                'school_class_id' => $schoolClass->id,
                'enrollment_date' => $data['enrollment_date'] ?? now()->toDateString(),
                'type' => $data['type'],
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ],
        );

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('success', 'Eleve rattache a la classe.');
    }

    public function detachStudent(SchoolClass $schoolClass, Enrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->school_class_id === $schoolClass->id, 404);

        $enrollment->delete();

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('success', 'Eleve retire de la classe.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();

        abort_if(! $academicYear, 422, 'Aucune annee scolaire active.');

        return $academicYear;
    }

    private function validateClass(Request $request, AcademicYear $academicYear, ?SchoolClass $schoolClass = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_classes')
                    ->where('academic_year_id', $academicYear->id)
                    ->ignore($schoolClass?->id),
            ],
            'code' => ['nullable', 'string', 'max:40'],
            'level_id' => ['required', 'exists:levels,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['required', 'in:active,inactive,archived'],
        ]);
    }
}
