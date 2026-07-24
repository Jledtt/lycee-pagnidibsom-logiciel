<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\MockExam;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintCenterWebController extends Controller
{
    public function __invoke(Request $request): View
    {
        $academicYears = AcademicYear::query()
            ->orderByDesc('is_active')
            ->latest('id')
            ->get();

        $academicYear = AcademicYear::query()->find($request->integer('academic_year_id'))
            ?? $academicYears->firstWhere('is_active', true)
            ?? $academicYears->first();

        $classes = SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $selectedClass = $classes->firstWhere('id', $request->integer('school_class_id')) ?? $classes->first();
        $studentSearch = trim($request->string('student')->toString());
        $documentTypes = [
            'all' => 'Tous les documents',
            'students' => 'Élèves et inscriptions',
            'finance' => 'Finances',
            'grades' => 'Notes et bulletins',
            'exams' => 'Examens',
            'attendance' => 'Vie scolaire',
        ];
        $documentType = $request->string('document_type')->toString() ?: 'all';

        if (! array_key_exists($documentType, $documentTypes)) {
            $documentType = 'all';
        }

        $students = Student::query()
            ->with(['documents', 'enrollments.schoolClass.level'])
            ->where('status', 'active')
            ->when($studentSearch !== '', fn ($query) => $query->where(function ($searchQuery) use ($studentSearch) {
                $searchQuery
                    ->where('first_name', 'like', '%' . $studentSearch . '%')
                    ->orWhere('last_name', 'like', '%' . $studentSearch . '%')
                    ->orWhere('matricule', 'like', '%' . $studentSearch . '%');
            }))
            ->when($selectedClass, fn ($query) => $query->whereHas('enrollments', function ($enrollmentQuery) use ($selectedClass, $academicYear) {
                $enrollmentQuery
                    ->where('school_class_id', $selectedClass->id)
                    ->when($academicYear, fn ($yearQuery) => $yearQuery->where('academic_year_id', $academicYear->id));
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(12)
            ->get();

        $exams = MockExam::query()
            ->with('classes.level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->latest('id')
            ->limit(8)
            ->get();

        return view('print-center.index', [
            'academicYear' => $academicYear,
            'academicYears' => $academicYears,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'studentSearch' => $studentSearch,
            'documentTypes' => $documentTypes,
            'documentType' => $documentType,
            'exams' => $exams,
            'firstClass' => $classes->first(),
        ]);
    }
}
