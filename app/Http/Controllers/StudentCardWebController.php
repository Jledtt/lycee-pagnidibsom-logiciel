<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolSetting;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Relations\Pivot;
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
        $fatherGuardian = $student->guardians->firstWhere('pivot.relationship', 'father')
            ?? $student->guardians->firstWhere('pivot.relationship', 'tutor');
        $motherGuardian = $student->guardians->firstWhere('pivot.relationship', 'mother');
        $filename = 'carte-scolaire-'.Str::slug($student->matricule.'-'.$student->full_name).'.pdf';

        return Pdf::loadView('students.school-card-pdf', [
            'academicYear' => $academicYear,
            'className' => $enrollment?->schoolClass?->name ?? $student->desired_class,
            'emergencyContact' => $this->emergencyContact($student),
            'fatherName' => $fatherGuardian?->full_name,
            'motherName' => $motherGuardian?->full_name,
            'photoPath' => $this->photoPath($student),
            'school' => $school,
            'student' => $student,
        ])
            ->setPaper('a4')
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
            return trim(($student->emergency_contact_name ? $student->emergency_contact_name.' - ' : '').$student->emergency_contact_phone);
        }

        $guardian = $student->guardians
            ->sortByDesc(function ($guardian): bool {
                $pivot = $guardian->getRelation('pivot');

                return $pivot instanceof Pivot && (bool) $pivot->getAttribute('is_primary');
            })
            ->first(fn ($guardian) => filled($guardian->phone_primary));

        return $guardian
            ? trim($guardian->full_name.' - '.$guardian->phone_primary)
            : '-';
    }

    private function photoPath(Student $student): ?string
    {
        $media = $student->getFirstMedia('student_photo');

        if ($media && file_exists($media->getPath())) {
            return $media->getPath();
        }

        if (filled($student->photo_path) && file_exists(public_path($student->photo_path))) {
            return public_path($student->photo_path);
        }

        $photoDocument = $student->documents
            ->where('document_type', 'photo')
            ->where('status', 'received')
            ->filter(fn ($document) => filled($document->file_path))
            ->sortByDesc('created_at')
            ->first();

        if (! $photoDocument) {
            return null;
        }

        foreach (['documents', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($photoDocument->file_path)) {
                return Storage::disk($disk)->path($photoDocument->file_path);
            }
        }

        return null;
    }
}
