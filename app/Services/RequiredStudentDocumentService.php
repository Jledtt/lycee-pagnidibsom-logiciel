<?php

namespace App\Services;

use App\Models\RequiredStudentDocument;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class RequiredStudentDocumentService
{
    public function requiredTypes(): array
    {
        return $this->baseRequiredTypes();
    }

    public function availableDocumentTypes(): array
    {
        return collect($this->baseDocumentTypes())
            ->merge($this->configuredTypes())
            ->sort()
            ->all();
    }

    public function requiredTypesForClass(?SchoolClass $schoolClass): array
    {
        if (! $this->requiredDocumentsTableExists()) {
            return $this->baseRequiredTypes();
        }

        $documents = RequiredStudentDocument::query()
            ->with('schoolClass.level')
            ->where('status', 'active')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->filter(fn (RequiredStudentDocument $document) => $document->appliesTo($schoolClass));

        if ($documents->isEmpty()) {
            return $this->baseRequiredTypes();
        }

        return $documents
            ->mapWithKeys(fn (RequiredStudentDocument $document) => [$document->document_type => $document->name])
            ->all();
    }

    public function baseDocumentTypes(): array
    {
        return [
            'birth_certificate' => 'Acte de naissance',
            'photo' => 'Photo',
            'previous_report_card' => 'Ancien bulletin',
            'certificate' => 'Certificat',
            'receipt' => 'Reçu',
            'parent_authorization' => 'Autorisation parentale',
            'identity' => 'Piece d identite',
            'other' => 'Autre document',
        ];
    }

    public function statusForStudent(Student $student, ?SchoolClass $schoolClass = null): array
    {
        $receivedTypes = $student->documents
            ->where('status', 'received')
            ->pluck('document_type')
            ->unique()
            ->all();

        return collect($this->requiredTypesForClass($schoolClass))
            ->map(fn (string $label, string $type) => [
                'type' => $type,
                'label' => $label,
                'is_received' => in_array($type, $receivedTypes, true),
            ])
            ->values()
            ->all();
    }

    public function missingForStudent(Student $student, ?SchoolClass $schoolClass = null): array
    {
        return collect($this->statusForStudent($student, $schoolClass))
            ->reject(fn (array $document) => $document['is_received'])
            ->values()
            ->all();
    }

    public function reportRows(Collection $enrollments): Collection
    {
        return $enrollments
            ->map(function ($enrollment) {
                $student = $enrollment->student;
                $schoolClass = $enrollment->schoolClass;
                $missingDocuments = $student ? $this->missingForStudent($student, $schoolClass) : [];

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'class' => $schoolClass,
                    'missing_documents' => $missingDocuments,
                    'missing_count' => count($missingDocuments),
                    'is_complete' => count($missingDocuments) === 0,
                ];
            })
            ->values();
    }

    public function summary(Collection $rows): array
    {
        return [
            'students' => $rows->count(),
            'complete' => $rows->where('is_complete', true)->count(),
            'incomplete' => $rows->where('is_complete', false)->count(),
            'missing_documents' => $rows->sum('missing_count'),
        ];
    }

    private function baseRequiredTypes(): array
    {
        return [
            'birth_certificate' => 'Acte de naissance',
            'photo' => 'Photo',
            'previous_report_card' => 'Ancien bulletin',
            'parent_authorization' => 'Autorisation parentale',
        ];
    }

    private function configuredTypes(): array
    {
        if (! $this->requiredDocumentsTableExists()) {
            return [];
        }

        return RequiredStudentDocument::query()
            ->orderBy('name')
            ->pluck('name', 'document_type')
            ->all();
    }

    private function requiredDocumentsTableExists(): bool
    {
        try {
            return Schema::hasTable('required_student_documents');
        } catch (\Throwable) {
            return false;
        }
    }
}
