<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;

class MatriculeGeneratorService
{
    public function generate(?AcademicYear $academicYear = null): string
    {
        $year = $academicYear?->starts_at?->format('Y') ?? now()->format('Y');
        $prefix = 'LPP-' . $year . '-';

        $lastStudent = Student::query()
            ->where('matricule', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($lastStudent !== null) {
            $lastNumber = (int) substr($lastStudent->matricule, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
