<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enrollments = Enrollment::query()
            ->with(['student', 'schoolClass.level', 'academicYear'])
            ->when($request->integer('school_class_id'), fn ($query, int $classId) => $query->where('school_class_id', $classId))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($enrollments);
    }

    public function store(Request $request, EnrollmentService $enrollmentService): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'type' => ['nullable', 'in:new,renewal,transfer'],
            'enrollment_date' => ['nullable', 'date'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $student = Student::findOrFail($data['student_id']);
        $schoolClass = SchoolClass::findOrFail($data['school_class_id']);

        $enrollment = $enrollmentService->enroll($student, $schoolClass, $academicYear, $data + [
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($enrollment->load(['student', 'schoolClass']), 201);
    }
}
