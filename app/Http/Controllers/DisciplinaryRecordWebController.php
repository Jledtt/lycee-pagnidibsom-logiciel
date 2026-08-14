<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\DisciplinaryRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DisciplinaryRecordWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DisciplinaryRecord::class);
        $academicYear = $this->activeAcademicYear();

        $records = DisciplinaryRecord::query()
            ->with(['student', 'schoolClass', 'creator'])
            ->when(
                $academicYear,
                fn (Builder $query) => $query->where('academic_year_id', $academicYear->id),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->when($request->string('search')->trim()->toString(), function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('title', 'like', "%{$search}%")
                        ->orWhereHas('student', function (Builder $studentQuery) use ($search): void {
                            $studentQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('matricule', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->integer('school_class_id'), fn (Builder $query, int $classId) => $query->where('school_class_id', $classId))
            ->when($request->integer('student_id'), fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when($request->string('type')->toString(), fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('record_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('discipline.index', [
            'academicYear' => $academicYear,
            'classes' => $this->classesForYear($academicYear),
            'filters' => $request->only(['search', 'school_class_id', 'student_id', 'type', 'status']),
            'records' => $records,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', DisciplinaryRecord::class);
        $academicYear = $this->requireActiveAcademicYear();
        $students = $this->studentsForYear($academicYear);
        $selectedStudentId = $request->integer('student_id');

        abort_if($selectedStudentId > 0 && ! $students->contains('id', $selectedStudentId), 404);

        return view('discipline.create', [
            'academicYear' => $academicYear,
            'record' => new DisciplinaryRecord([
                'record_date' => $this->defaultRecordDate($academicYear),
                'status' => 'active',
                'type' => 'observation',
            ]),
            'selectedStudentId' => $selectedStudentId,
            'students' => $students,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DisciplinaryRecord::class);
        $academicYear = $this->requireActiveAcademicYear();
        $studentId = (int) $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->whereNull('deleted_at')],
        ])['student_id'];
        $student = Student::query()->findOrFail($studentId);
        $enrollment = $this->currentEnrollment($student, $academicYear);

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'student_id' => 'Cet élève doit avoir une inscription active pour l’année scolaire en cours.',
            ]);
        }

        $record = DisciplinaryRecord::query()->create([
            ...$this->validateIncident($request, $academicYear),
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $enrollment->school_class_id,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('discipline.show', $record)
            ->with('success', 'Incident disciplinaire enregistré.');
    }

    public function show(DisciplinaryRecord $discipline): View
    {
        $this->authorize('view', $discipline);
        $discipline->load(['academicYear', 'student', 'schoolClass', 'creator', 'resolver', 'canceller']);

        return view('discipline.show', [
            'academicYear' => $this->activeAcademicYear(),
            'record' => $discipline,
        ]);
    }

    public function edit(DisciplinaryRecord $discipline): View
    {
        $this->authorize('update', $discipline);
        abort_unless($discipline->status === 'active', 409, 'Seul un incident actif peut être modifié.');
        $discipline->load(['academicYear', 'student', 'schoolClass']);

        return view('discipline.edit', [
            'academicYear' => $this->activeAcademicYear(),
            'record' => $discipline,
        ]);
    }

    public function update(Request $request, DisciplinaryRecord $discipline): RedirectResponse
    {
        $this->authorize('update', $discipline);
        abort_unless($discipline->status === 'active', 409, 'Seul un incident actif peut être modifié.');
        $discipline->loadMissing('academicYear');
        $discipline->update($this->validateIncident($request, $discipline->academicYear));

        return redirect()
            ->route('discipline.show', $discipline)
            ->with('success', 'Incident disciplinaire mis à jour.');
    }

    public function resolve(Request $request, DisciplinaryRecord $discipline): RedirectResponse
    {
        $this->authorize('update', $discipline);
        $data = $request->validate([
            'action_taken' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($discipline, $data, $request): void {
            $record = DisciplinaryRecord::query()->lockForUpdate()->findOrFail($discipline->id);

            if ($record->status !== 'active') {
                throw ValidationException::withMessages([
                    'status' => 'Cet incident a déjà été traité.',
                ]);
            }

            $record->update([
                'status' => 'resolved',
                'action_taken' => $data['action_taken'],
                'resolved_at' => now(),
                'resolved_by' => $request->user()->id,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ]);
        });

        return back()->with('success', 'Incident marqué comme résolu.');
    }

    public function cancel(Request $request, DisciplinaryRecord $discipline): RedirectResponse
    {
        $this->authorize('update', $discipline);
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($discipline, $data, $request): void {
            $record = DisciplinaryRecord::query()->lockForUpdate()->findOrFail($discipline->id);

            if ($record->status !== 'active') {
                throw ValidationException::withMessages([
                    'status' => 'Cet incident a déjà été traité.',
                ]);
            }

            $record->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
                'cancellation_reason' => $data['cancellation_reason'],
                'resolved_at' => null,
                'resolved_by' => null,
            ]);
        });

        return back()->with('success', 'Incident annulé avec conservation de l’historique.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIncident(Request $request, AcademicYear $academicYear): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['observation', 'warning', 'sanction'])],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:5000'],
            'record_date' => [
                'required',
                'date',
                'after_or_equal:'.$academicYear->starts_at->format('Y-m-d'),
                'before_or_equal:'.$academicYear->ends_at->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * @return Collection<int, Student>
     */
    private function studentsForYear(AcademicYear $academicYear): Collection
    {
        return Student::query()
            ->where('status', 'active')
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'active'))
            ->with(['enrollments' => fn ($query) => $query
                ->with('schoolClass')
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'active')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function classesForYear(?AcademicYear $academicYear): Collection
    {
        return SchoolClass::query()
            ->when(
                $academicYear,
                fn (Builder $query) => $query->where('academic_year_id', $academicYear->id),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->orderBy('name')
            ->get();
    }

    private function currentEnrollment(Student $student, AcademicYear $academicYear): ?Enrollment
    {
        return Enrollment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();
        abort_if($academicYear === null, 422, 'Aucune année scolaire active.');

        return $academicYear;
    }

    private function defaultRecordDate(AcademicYear $academicYear): string
    {
        $today = now()->startOfDay();

        if ($today->lt($academicYear->starts_at)) {
            return $academicYear->starts_at->format('Y-m-d');
        }

        if ($today->gt($academicYear->ends_at)) {
            return $academicYear->ends_at->format('Y-m-d');
        }

        return $today->format('Y-m-d');
    }
}
