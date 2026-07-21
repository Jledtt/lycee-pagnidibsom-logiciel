<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\MockExam;
use App\Models\SchoolClass;
use Illuminate\View\View;

class PrintCenterWebController extends Controller
{
    public function __invoke(): View
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        $classes = SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $exams = MockExam::query()
            ->with('classes.level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->latest('id')
            ->limit(8)
            ->get();

        return view('print-center.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'exams' => $exams,
            'firstClass' => $classes->first(),
        ]);
    }
}
