<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportWebController extends Controller
{
    public function classList(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        if ($schoolClass) {
            $schoolClass = $this->loadClassList($schoolClass);
        }

        return view('reports.class-list', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'filters' => $request->only(['school_class_id']),
            'schoolClass' => $schoolClass,
            'summary' => $this->classSummary($schoolClass),
        ]);
    }

    public function classListPdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $schoolClass = $this->loadClassList($schoolClass);
        $filename = 'liste-eleves-' . Str::slug($schoolClass->name . '-' . ($academicYear?->name ?? 'annee')) . '.pdf';

        return Pdf::loadView('reports.class-list-pdf', [
            'academicYear' => $academicYear,
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
            'summary' => $this->classSummary($schoolClass),
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
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
        $selectedId = $request->integer('school_class_id');

        if ($selectedId > 0) {
            return $classes->firstWhere('id', $selectedId);
        }

        return $classes->first();
    }

    private function loadClassList(SchoolClass $schoolClass): SchoolClass
    {
        return $schoolClass->load([
            'level',
            'academicYear',
            'enrollments' => fn ($query) => $query
                ->with(['student.guardians'])
                ->where('status', 'active')
                ->whereHas('student', fn ($studentQuery) => $studentQuery->where('status', 'active'))
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->orderBy('students.last_name')
                ->orderBy('students.first_name')
                ->select('enrollments.*'),
        ]);
    }

    private function classSummary(?SchoolClass $schoolClass): array
    {
        if (! $schoolClass) {
            return [
                'total' => 0,
                'girls' => 0,
                'boys' => 0,
            ];
        }

        $students = $schoolClass->enrollments->pluck('student')->filter();

        return [
            'total' => $students->count(),
            'girls' => $students->where('gender', 'female')->count(),
            'boys' => $students->where('gender', 'male')->count(),
        ];
    }
}
