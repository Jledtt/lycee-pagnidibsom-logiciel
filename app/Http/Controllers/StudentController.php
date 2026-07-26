<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\CommunicationService;
use App\Services\MatriculeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $students = Student::query()
            ->with(['guardians', 'enrollments.schoolClass'])
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($students);
    }

    public function store(Request $request, MatriculeGeneratorService $matriculeGenerator): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
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

    public function show(Student $student): JsonResponse
    {
        return response()->json(
            $student->load(['guardians', 'enrollments.schoolClass.level', 'payments.lines.feeType'])
        );
    }

    public function update(
        Request $request,
        Student $student,
        CommunicationService $communicationService,
    ): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
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
        $student->delete();

        return response()->json(null, 204);
    }
}
