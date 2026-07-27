<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;

class MatriculeGeneratorService
{
    public function __construct(private readonly OfficialNumberService $officialNumberService) {}

    public function generate(?AcademicYear $academicYear = null): string
    {
        return $this->officialNumberService->generate(
            OfficialNumberService::STUDENT_MATRICULE,
            fn (string $number) => Student::query()->where('matricule', $number)->exists(),
            $academicYear,
        );
    }
}
