<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Rules\PlausibleStudentBirthDate;
use App\Services\CommunicationService;
use App\Services\MatriculeGeneratorService;
use App\Services\SchoolAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(private readonly SchoolAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Student::class);

        $students = $this->access->scopeStudents(Student::query(), $request->user())
            ->with('enrollments.schoolClass')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $students->through(fn (Student $student) => $this->identityPayload($student));

        return response()->json($students);
    }

    public function store(Request $request, MatriculeGeneratorService $matriculeGenerator): JsonResponse
    {
        $this->authorize('create', Student::class);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date', new PlausibleStudentBirthDate],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'health_notes' => ['nullable', 'string'],
        ]);

        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        $student = Student::create($data + [
            'matricule' => $matriculeGenerator->generate($academicYear),
            'status' => 'active',
        ]);

        return response()->json($student, 201);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        if (! $this->access->canViewFullStudentRecord($request->user())) {
            $student->load('enrollments.schoolClass.level');

            return response()->json($this->identityPayload($student));
        }

        $relations = ['guardians', 'enrollments.schoolClass.level'];

        if ($request->user()->can('payments.view')) {
            $relations[] = 'payments.lines.feeType';
        }

        $student->load($relations);

        return response()->json($student);
    }

    public function update(
        Request $request,
        Student $student,
        CommunicationService $communicationService,
    ): JsonResponse {
        $this->authorize('update', $student);

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date', new PlausibleStudentBirthDate],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'health_notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:active,transferred,dropped,graduated,suspended'],
        ]);

        $oldStatus = (string) $student->status;
        $student->update($data);

        $communicationService->queueStudentStatusChange(
            $student,
            $oldStatus,
            (string) $student->status,
            $request->user(),
        );

        return response()->json($student);
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->authorize('delete', $student);

        $student->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{
     *     id: int,
     *     matricule: string,
     *     first_name: string,
     *     last_name: string,
     *     full_name: string,
     *     gender: string|null,
     *     status: string,
     *     current_class: array{id: int, name: string}|null
     * }
     */
    private function identityPayload(Student $student): array
    {
        $enrollment = $student->enrollments
            ->where('status', 'active')
            ->sortByDesc('id')
            ->first();

        return [
            'id' => $student->id,
            'matricule' => $student->matricule,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'full_name' => $student->full_name,
            'gender' => $student->gender,
            'status' => $student->status,
            'current_class' => $enrollment?->schoolClass ? [
                'id' => $enrollment->schoolClass->id,
                'name' => $enrollment->schoolClass->name,
            ] : null,
        ];
    }
}
