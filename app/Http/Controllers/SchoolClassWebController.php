<?php

namespace App\Http\Controllers;

use App\Models\AcademicTrack;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SchoolClassWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();

        $classes = SchoolClass::query()
            ->with(['level', 'academicTrack'])
            ->withCount(['enrollments' => fn ($query) => $query->where('status', 'active')])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('academicTrack', function ($trackQuery) use ($search): void {
                            $trackQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->integer('academic_track_id'), fn ($query, int $trackId) => $query->where('academic_track_id', $trackId))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('classes.index', [
            'academicYear' => $academicYear,
            'academicTracks' => AcademicTrack::query()->orderBy('kind')->orderBy('name')->get(),
            'classes' => $classes,
            'filters' => $request->only(['search', 'academic_track_id', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('classes.create', [
            'academicYear' => $this->activeAcademicYear(),
            'schoolClass' => new SchoolClass(['status' => 'active']),
            'levels' => Level::query()->orderBy('position')->get(),
            'academicTracks' => $this->availableAcademicTracks(),
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
            ->with('success', 'Classe créée avec succès.');
    }

    public function show(SchoolClass $schoolClass): View
    {
        $academicYear = $this->activeAcademicYear();

        $schoolClass->load([
            'level',
            'academicTrack',
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
            'academicTracks' => $this->availableAcademicTracks($schoolClass),
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $schoolClass->update($this->validateClass($request, $academicYear, $schoolClass));

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('success', 'Classe mise à jour.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->update(['status' => 'archived']);

        return redirect()
            ->route('classes.index')
            ->with('success', 'Classe archivée.');
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
            ->with('success', 'Élève rattaché à la classe.');
    }

    public function detachStudent(SchoolClass $schoolClass, Enrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->school_class_id === $schoolClass->id, 404);

        $enrollment->delete();

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('success', 'Élève retiré de la classe.');
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

    private function validateClass(Request $request, AcademicYear $academicYear, ?SchoolClass $schoolClass = null): array
    {
        $data = $request->validate([
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
            'academic_track_id' => ['nullable', 'integer', 'exists:academic_tracks,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['required', 'in:active,inactive,archived'],
        ]);

        if (filled($data['academic_track_id'] ?? null)) {
            $academicTrack = AcademicTrack::query()->findOrFail($data['academic_track_id']);
            $keepsCurrentInactiveTrack = $schoolClass?->academic_track_id === $academicTrack->id;

            if ($academicTrack->status !== 'active' && ! $keepsCurrentInactiveTrack) {
                throw ValidationException::withMessages([
                    'academic_track_id' => 'Cette série ou filière est désactivée et ne peut pas être affectée à une nouvelle classe.',
                ]);
            }
        }

        return $data;
    }

    /** @return Collection<int, AcademicTrack> */
    private function availableAcademicTracks(?SchoolClass $schoolClass = null): Collection
    {
        return AcademicTrack::query()
            ->where(function (Builder $query) use ($schoolClass): void {
                $query->where('status', 'active');

                if ($schoolClass?->academic_track_id !== null) {
                    $query->orWhere('id', $schoolClass->academic_track_id);
                }
            })
            ->orderBy('kind')
            ->orderBy('name')
            ->get();
    }
}
