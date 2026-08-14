<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\GuardianAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuardianWebController extends Controller
{
    public function __construct(
        private readonly GuardianAssignmentService $assignments,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status']);
        $guardians = Guardian::query()
            ->withCount('students')
            ->when($request->string('search')->trim()->toString(), function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone_primary', 'like', "%{$search}%")
                        ->orWhere('phone_secondary', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->string('status')->toString(),
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('guardians.index', [
            'academicYear' => $this->activeAcademicYear(),
            'filters' => $filters,
            'guardians' => $guardians,
        ]);
    }

    public function create(): View
    {
        $academicYear = $this->activeAcademicYear();

        return view('guardians.create', [
            'academicYear' => $academicYear,
            'guardian' => new Guardian(['status' => 'active']),
            'students' => $this->studentsForLinking($academicYear),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $guardianData = $this->validateGuardian($request);
        $linkData = $this->validateLink($request);

        $guardian = DB::transaction(function () use ($guardianData, $linkData): Guardian {
            $guardian = Guardian::query()->create($guardianData);
            $student = Student::query()->findOrFail($linkData['student_id']);

            $this->assignments->attach(
                $guardian,
                $student,
                $linkData['relationship'],
                $linkData['is_primary'],
                $linkData['can_receive_sms'],
                $linkData['can_pickup_child'],
            );

            return $guardian;
        });

        return redirect()
            ->route('guardians.show', $guardian)
            ->with('success', 'Responsable légal créé et rattaché à l’élève.');
    }

    public function show(Guardian $guardian): View
    {
        $academicYear = $this->activeAcademicYear();
        $guardian->load([
            'students' => fn ($query) => $query
                ->with(['enrollments' => fn ($enrollmentQuery) => $enrollmentQuery
                    ->with('schoolClass')
                    ->when($academicYear, fn ($yearQuery) => $yearQuery
                        ->where('academic_year_id', $academicYear->id))
                    ->where('status', 'active')])
                ->orderBy('last_name')
                ->orderBy('first_name'),
        ]);

        return view('guardians.show', [
            'academicYear' => $academicYear,
            'availableStudents' => $this->studentsForLinking($academicYear, $guardian),
            'guardian' => $guardian,
        ]);
    }

    public function edit(Guardian $guardian): View
    {
        return view('guardians.edit', [
            'academicYear' => $this->activeAcademicYear(),
            'guardian' => $guardian,
        ]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $guardian->update($this->validateGuardian($request));

        return redirect()
            ->route('guardians.show', $guardian)
            ->with('success', 'Fiche du responsable légal mise à jour.');
    }

    public function attachStudent(Request $request, Guardian $guardian): RedirectResponse
    {
        $data = $this->validateLink($request);
        $student = Student::query()->findOrFail($data['student_id']);

        $this->assignments->attach(
            $guardian,
            $student,
            $data['relationship'],
            $data['is_primary'],
            $data['can_receive_sms'],
            $data['can_pickup_child'],
        );

        return back()->with('success', 'Élève rattaché au responsable légal.');
    }

    public function updateStudent(Request $request, Guardian $guardian, Student $student): RedirectResponse
    {
        $this->guardianStudentLink($guardian, $student);
        $data = $this->validateRelationship($request);

        $this->assignments->attach(
            $guardian,
            $student,
            $data['relationship'],
            $data['is_primary'],
            $data['can_receive_sms'],
            $data['can_pickup_child'],
        );

        return back()->with('success', 'Relation avec l’élève mise à jour.');
    }

    public function detachStudent(Guardian $guardian, Student $student): RedirectResponse
    {
        $this->guardianStudentLink($guardian, $student);
        $this->assignments->detach($guardian, $student);

        return back()->with('success', 'Élève retiré de cette fiche de responsable.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGuardian(Request $request): array
    {
        return $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone_primary' => ['required', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'profession' => ['nullable', 'string', 'max:160'],
            'service' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    /**
     * @return array{student_id: int, relationship: string, is_primary: bool, can_receive_sms: bool, can_pickup_child: bool}
     */
    private function validateLink(Request $request): array
    {
        $data = $request->validate([
            'student_id' => [
                'required',
                Rule::exists('students', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            ...$this->relationshipRules(),
        ]);

        return [
            'student_id' => (int) $data['student_id'],
            'relationship' => (string) $data['relationship'],
            'is_primary' => $request->boolean('is_primary'),
            'can_receive_sms' => $request->boolean('can_receive_sms'),
            'can_pickup_child' => $request->boolean('can_pickup_child'),
        ];
    }

    /**
     * @return array{relationship: string, is_primary: bool, can_receive_sms: bool, can_pickup_child: bool}
     */
    private function validateRelationship(Request $request): array
    {
        $data = $request->validate($this->relationshipRules());

        return [
            'relationship' => (string) $data['relationship'],
            'is_primary' => $request->boolean('is_primary'),
            'can_receive_sms' => $request->boolean('can_receive_sms'),
            'can_pickup_child' => $request->boolean('can_pickup_child'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function relationshipRules(): array
    {
        return [
            'relationship' => ['required', Rule::in(['father', 'mother', 'tutor', 'other'])],
            'is_primary' => ['nullable', 'boolean'],
            'can_receive_sms' => ['nullable', 'boolean'],
            'can_pickup_child' => ['nullable', 'boolean'],
        ];
    }

    private function guardianStudentLink(Guardian $guardian, Student $student): object
    {
        $link = DB::table('guardian_student')
            ->where('guardian_id', $guardian->id)
            ->where('student_id', $student->id)
            ->first();

        abort_unless($link !== null, 404);

        return $link;
    }

    /**
     * @return Collection<int, Student>
     */
    private function studentsForLinking(?AcademicYear $academicYear, ?Guardian $guardian = null): Collection
    {
        return Student::query()
            ->when($guardian, fn (Builder $query) => $query->whereDoesntHave(
                'guardians',
                fn (Builder $guardianQuery) => $guardianQuery->whereKey($guardian->id),
            ))
            ->where('status', 'active')
            ->with(['enrollments' => fn ($query) => $query
                ->with('schoolClass')
                ->when($academicYear, fn ($yearQuery) => $yearQuery
                    ->where('academic_year_id', $academicYear->id))
                ->where('status', 'active')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }
}
