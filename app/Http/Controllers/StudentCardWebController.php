<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolSetting;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentCardWebController extends Controller
{
    public function pdf(Student $student)
    {
        $academicYear = $this->activeAcademicYear();
        $student->load(['guardians', 'documents']);
        $enrollment = $this->currentEnrollment($student, $academicYear);
        $school = SchoolSetting::query()->first();
        $filename = 'carte-scolaire-' . Str::slug($student->matricule . '-' . $student->full_name) . '.pdf';

        return Pdf::loadView('students.school-card-pdf', [
            'academicYear' => $academicYear,
            'className' => $enrollment?->schoolClass?->name ?? $student->desired_class,
            'emergencyContact' => $this->emergencyContact($student),
            'photoPath' => $this->photoPath($student),
            'principalName' => $school?->principal_name ?: 'Yamdaogo TINTILA',
            'school' => $school,
            'student' => $student,
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function currentEnrollment(Student $student, ?AcademicYear $academicYear): ?Enrollment
    {
        return Enrollment::query()
            ->with('schoolClass.level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    private function emergencyContact(Student $student): string
    {
        if (filled($student->emergency_contact_phone)) {
            return trim(($student->emergency_contact_name ? $student->emergency_contact_name . ' - ' : '') . $student->emergency_contact_phone);
        }

        $guardian = $student->guardians
            ->sortByDesc(fn ($guardian) => (bool) $guardian->pivot?->is_primary)
            ->first(fn ($guardian) => filled($guardian->phone_primary));

        return $guardian
            ? trim($guardian->full_name . ' - ' . $guardian->phone_primary)
            : '-';
    }

    private function photoPath(Student $student): ?string
    {
        if (filled($student->photo_path) && file_exists(public_path($student->photo_path))) {
            return public_path($student->photo_path);
        }

        $photoDocument = $student->documents
            ->where('document_type', 'photo')
            ->where('status', 'received')
            ->filter(fn ($document) => filled($document->file_path) && Storage::disk('public')->exists($document->file_path))
            ->sortByDesc('created_at')
            ->first();

        return $photoDocument
            ? Storage::disk('public')->path($photoDocument->file_path)
            : null;
    }
}
